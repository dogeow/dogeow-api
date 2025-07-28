<?php

namespace App\Services;

use App\Models\ChatRoom;
use App\Models\ChatMessage;
use App\Models\ChatRoomUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;

class ChatCacheService
{
    // Cache TTL constants (in seconds)
    private const ROOM_LIST_TTL = 300; // 5 minutes
    private const ROOM_STATS_TTL = 600; // 10 minutes
    private const ONLINE_USERS_TTL = 60; // 1 minute
    private const MESSAGE_HISTORY_TTL = 1800; // 30 minutes
    private const USER_PRESENCE_TTL = 120; // 2 minutes
    private const RATE_LIMIT_TTL = 3600; // 1 hour

    // Cache key prefixes
    private const PREFIX_ROOM_LIST = 'chat:rooms:list';
    private const PREFIX_ROOM_STATS = 'chat:room:stats:';
    private const PREFIX_ONLINE_USERS = 'chat:room:online:';
    private const PREFIX_MESSAGE_HISTORY = 'chat:room:messages:';
    private const PREFIX_USER_PRESENCE = 'chat:user:presence:';
    private const PREFIX_RATE_LIMIT = 'chat:rate_limit:';
    private const PREFIX_ROOM_ACTIVITY = 'chat:room:activity:';

    /**
     * Get cached room list or fetch from database
     */
    public function getRoomList(): Collection
    {
        return Cache::remember(self::PREFIX_ROOM_LIST, self::ROOM_LIST_TTL, function () {
            return ChatRoom::where('is_active', true)
                ->with('creator:id,name,email')
                ->withCount([
                    'users as online_count' => function ($query) {
                        $query->where('is_online', true);
                    },
                    'messages as message_count'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
        });
    }

    /**
     * Invalidate room list cache
     */
    public function invalidateRoomList(): void
    {
        Cache::forget(self::PREFIX_ROOM_LIST);
    }

    /**
     * Get cached room statistics
     */
    public function getRoomStats(int $roomId): array
    {
        $cacheKey = self::PREFIX_ROOM_STATS . $roomId;
        
        return Cache::remember($cacheKey, self::ROOM_STATS_TTL, function () use ($roomId) {
            $room = ChatRoom::with('creator:id,name,email')->find($roomId);
            
            if (!$room) {
                return [];
            }

            $totalUsers = ChatRoomUser::where('room_id', $roomId)->count();
            $onlineUsers = ChatRoomUser::where('room_id', $roomId)->where('is_online', true)->count();
            
            $messageStats = [
                'total_messages' => ChatMessage::where('room_id', $roomId)->count(),
                'text_messages' => ChatMessage::where('room_id', $roomId)->where('message_type', 'text')->count(),
                'system_messages' => ChatMessage::where('room_id', $roomId)->where('message_type', 'system')->count(),
            ];

            $recentActivity = ChatMessage::where('room_id', $roomId)
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            return [
                'room' => $room,
                'total_users' => $totalUsers,
                'online_users' => $onlineUsers,
                'messages' => $messageStats,
                'recent_activity_24h' => $recentActivity,
                'created_at' => $room->created_at,
                'last_activity' => ChatMessage::where('room_id', $roomId)->latest()->first()?->created_at
            ];
        });
    }

    /**
     * Invalidate room statistics cache
     */
    public function invalidateRoomStats(int $roomId): void
    {
        Cache::forget(self::PREFIX_ROOM_STATS . $roomId);
    }

    /**
     * Get cached online users for a room
     */
    public function getOnlineUsers(int $roomId): Collection
    {
        $cacheKey = self::PREFIX_ONLINE_USERS . $roomId;
        
        return Cache::remember($cacheKey, self::ONLINE_USERS_TTL, function () use ($roomId) {
            return ChatRoomUser::where('room_id', $roomId)
                ->where('is_online', true)
                ->with('user:id,name,email')
                ->orderBy('joined_at', 'asc')
                ->get()
                ->map(function ($roomUser) {
                    return [
                        'id' => $roomUser->user->id,
                        'name' => $roomUser->user->name,
                        'email' => $roomUser->user->email,
                        'joined_at' => $roomUser->joined_at,
                        'last_seen_at' => $roomUser->last_seen_at,
                        'is_online' => $roomUser->is_online
                    ];
                });
        });
    }

    /**
     * Invalidate online users cache for a room
     */
    public function invalidateOnlineUsers(int $roomId): void
    {
        Cache::forget(self::PREFIX_ONLINE_USERS . $roomId);
    }

    /**
     * Cache message history page
     */
    public function cacheMessageHistory(int $roomId, int $page, Collection $messages): void
    {
        $cacheKey = self::PREFIX_MESSAGE_HISTORY . "{$roomId}:page:{$page}";
        Cache::put($cacheKey, $messages, self::MESSAGE_HISTORY_TTL);
    }

    /**
     * Get cached message history page
     */
    public function getMessageHistory(int $roomId, int $page): ?Collection
    {
        $cacheKey = self::PREFIX_MESSAGE_HISTORY . "{$roomId}:page:{$page}";
        return Cache::get($cacheKey);
    }

    /**
     * Invalidate message history cache for a room
     */
    public function invalidateMessageHistory(int $roomId): void
    {
        $pattern = self::PREFIX_MESSAGE_HISTORY . "{$roomId}:page:*";
        $this->deleteByPattern($pattern);
    }

    /**
     * Cache user presence status
     */
    public function cacheUserPresence(int $userId, int $roomId, array $presenceData): void
    {
        $cacheKey = self::PREFIX_USER_PRESENCE . "{$userId}:{$roomId}";
        Cache::put($cacheKey, $presenceData, self::USER_PRESENCE_TTL);
    }

    /**
     * Get cached user presence status
     */
    public function getUserPresence(int $userId, int $roomId): ?array
    {
        $cacheKey = self::PREFIX_USER_PRESENCE . "{$userId}:{$roomId}";
        return Cache::get($cacheKey);
    }

    /**
     * Invalidate user presence cache
     */
    public function invalidateUserPresence(int $userId, int $roomId): void
    {
        Cache::forget(self::PREFIX_USER_PRESENCE . "{$userId}:{$roomId}");
    }

    /**
     * Implement rate limiting with Redis
     */
    public function checkRateLimit(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $cacheKey = self::PREFIX_RATE_LIMIT . $key;
        
        try {
            $redis = Redis::connection();
            $current = $redis->get($cacheKey);
            
            if ($current === null) {
                // First request in window
                $redis->setex($cacheKey, $windowSeconds, 1);
                return [
                    'allowed' => true,
                    'attempts' => 1,
                    'remaining' => $maxAttempts - 1,
                    'reset_time' => now()->addSeconds($windowSeconds)
                ];
            }
            
            $attempts = (int) $current;
            
            if ($attempts >= $maxAttempts) {
                $ttl = $redis->ttl($cacheKey);
                return [
                    'allowed' => false,
                    'attempts' => $attempts,
                    'remaining' => 0,
                    'reset_time' => now()->addSeconds($ttl > 0 ? $ttl : $windowSeconds)
                ];
            }
            
            // Increment counter
            $newAttempts = $redis->incr($cacheKey);
            
            return [
                'allowed' => true,
                'attempts' => $newAttempts,
                'remaining' => max(0, $maxAttempts - $newAttempts),
                'reset_time' => now()->addSeconds($redis->ttl($cacheKey))
            ];
            
        } catch (\Exception $e) {
            // Fallback to allowing request if Redis fails
            return [
                'allowed' => true,
                'attempts' => 1,
                'remaining' => $maxAttempts - 1,
                'reset_time' => now()->addSeconds($windowSeconds),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Track room activity for analytics
     */
    public function trackRoomActivity(int $roomId, string $activityType, int $userId = null): void
    {
        $cacheKey = self::PREFIX_ROOM_ACTIVITY . "{$roomId}:" . date('Y-m-d-H');
        
        try {
            $redis = Redis::connection();
            $activityData = [
                'type' => $activityType,
                'user_id' => $userId,
                'timestamp' => now()->timestamp
            ];
            
            // Store as a list with expiration
            $redis->lpush($cacheKey, json_encode($activityData));
            $redis->expire($cacheKey, 86400); // 24 hours
            
            // Keep only last 1000 activities per hour
            $redis->ltrim($cacheKey, 0, 999);
            
        } catch (\Exception $e) {
            // Silently fail for activity tracking
            \Log::warning('Failed to track room activity', [
                'room_id' => $roomId,
                'activity_type' => $activityType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get room activity analytics
     */
    public function getRoomActivity(int $roomId, int $hours = 24): array
    {
        try {
            $redis = Redis::connection();
            $activities = [];
            
            // Get activities for the last N hours
            for ($i = 0; $i < $hours; $i++) {
                $hour = now()->subHours($i)->format('Y-m-d-H');
                $cacheKey = self::PREFIX_ROOM_ACTIVITY . "{$roomId}:{$hour}";
                
                $hourlyActivities = $redis->lrange($cacheKey, 0, -1);
                foreach ($hourlyActivities as $activity) {
                    $activities[] = json_decode($activity, true);
                }
            }
            
            // Sort by timestamp descending
            usort($activities, function ($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
            
            return array_slice($activities, 0, 500); // Return last 500 activities
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Warm up cache for frequently accessed data
     */
    public function warmUpCache(): void
    {
        // Warm up room list
        $this->getRoomList();
        
        // Warm up stats for active rooms
        $activeRooms = ChatRoom::where('is_active', true)->pluck('id');
        foreach ($activeRooms as $roomId) {
            $this->getRoomStats($roomId);
            $this->getOnlineUsers($roomId);
        }
    }

    /**
     * Clear all chat-related cache
     */
    public function clearAllCache(): void
    {
        $patterns = [
            self::PREFIX_ROOM_LIST,
            self::PREFIX_ROOM_STATS . '*',
            self::PREFIX_ONLINE_USERS . '*',
            self::PREFIX_MESSAGE_HISTORY . '*',
            self::PREFIX_USER_PRESENCE . '*',
            self::PREFIX_ROOM_ACTIVITY . '*'
        ];
        
        foreach ($patterns as $pattern) {
            $this->deleteByPattern($pattern);
        }
    }

    /**
     * Delete cache keys by pattern (Redis specific)
     */
    private function deleteByPattern(string $pattern): void
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $keys = $redis->keys($pattern);
                if (!empty($keys)) {
                    $redis->del($keys);
                }
            } else {
                // For non-Redis cache drivers, we can't delete by pattern
                // This is a limitation of other cache drivers
                \Log::info('Pattern-based cache deletion not supported for current cache driver');
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to delete cache by pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $info = $redis->info();
                
                return [
                    'driver' => 'redis',
                    'memory_usage' => $info['used_memory_human'] ?? 'N/A',
                    'connected_clients' => $info['connected_clients'] ?? 'N/A',
                    'total_commands_processed' => $info['total_commands_processed'] ?? 'N/A',
                    'keyspace_hits' => $info['keyspace_hits'] ?? 'N/A',
                    'keyspace_misses' => $info['keyspace_misses'] ?? 'N/A',
                ];
            }
            
            return [
                'driver' => config('cache.default'),
                'message' => 'Detailed stats only available for Redis driver'
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => 'Failed to get cache stats: ' . $e->getMessage()
            ];
        }
    }
}