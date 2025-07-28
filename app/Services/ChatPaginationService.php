<?php

namespace App\Services;

use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ChatPaginationService
{
    private const DEFAULT_PAGE_SIZE = 50;
    private const MAX_PAGE_SIZE = 100;

    /**
     * Get messages using cursor-based pagination for better performance
     * 
     * @param int $roomId
     * @param string|null $cursor Base64 encoded cursor containing last message ID and timestamp
     * @param int $limit Number of messages to fetch
     * @param string $direction 'before' for older messages, 'after' for newer messages
     * @return array
     */
    public function getMessagesCursor(
        int $roomId, 
        ?string $cursor = null, 
        int $limit = self::DEFAULT_PAGE_SIZE,
        string $direction = 'before'
    ): array {
        // Ensure limit doesn't exceed maximum
        $limit = min($limit, self::MAX_PAGE_SIZE);
        
        $query = ChatMessage::with(['user:id,name,email'])
            ->where('room_id', $roomId);

        // Parse cursor if provided
        $cursorData = $this->parseCursor($cursor);
        
        if ($cursorData) {
            if ($direction === 'before') {
                // Get older messages (pagination backwards)
                $query->where(function (Builder $q) use ($cursorData) {
                    $q->where('created_at', '<', $cursorData['timestamp'])
                      ->orWhere(function (Builder $subQ) use ($cursorData) {
                          $subQ->where('created_at', '=', $cursorData['timestamp'])
                               ->where('id', '<', $cursorData['id']);
                      });
                })
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc');
            } else {
                // Get newer messages (real-time updates)
                $query->where(function (Builder $q) use ($cursorData) {
                    $q->where('created_at', '>', $cursorData['timestamp'])
                      ->orWhere(function (Builder $subQ) use ($cursorData) {
                          $subQ->where('created_at', '=', $cursorData['timestamp'])
                               ->where('id', '>', $cursorData['id']);
                      });
                })
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc');
            }
        } else {
            // No cursor provided, get most recent messages
            $query->orderBy('created_at', 'desc')
                  ->orderBy('id', 'desc');
        }

        // Fetch one extra to determine if there are more pages
        $messages = $query->limit($limit + 1)->get();
        
        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages = $messages->slice(0, $limit);
        }

        // For 'before' direction, reverse the order to show chronologically
        if ($direction === 'before' && !$cursor) {
            $messages = $messages->reverse()->values();
        }

        // Generate cursors for pagination
        $nextCursor = null;
        $prevCursor = null;
        
        if ($messages->isNotEmpty()) {
            if ($direction === 'before' || !$cursor) {
                // For backward pagination or initial load
                $firstMessage = $messages->first();
                $lastMessage = $messages->last();
                
                $nextCursor = $hasMore ? $this->generateCursor($lastMessage->id, $lastMessage->created_at) : null;
                $prevCursor = $this->generateCursor($firstMessage->id, $firstMessage->created_at);
            } else {
                // For forward pagination (newer messages)
                $firstMessage = $messages->first();
                $lastMessage = $messages->last();
                
                $nextCursor = $hasMore ? $this->generateCursor($lastMessage->id, $lastMessage->created_at) : null;
                $prevCursor = $this->generateCursor($firstMessage->id, $firstMessage->created_at);
            }
        }

        return [
            'messages' => $messages->values(),
            'pagination' => [
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'prev_cursor' => $prevCursor,
                'direction' => $direction,
                'limit' => $limit,
                'count' => $messages->count()
            ]
        ];
    }

    /**
     * Get recent messages for initial room load (optimized)
     * 
     * @param int $roomId
     * @param int $limit
     * @return array
     */
    public function getRecentMessages(int $roomId, int $limit = self::DEFAULT_PAGE_SIZE): array
    {
        $limit = min($limit, self::MAX_PAGE_SIZE);
        
        // Use cursor pagination for consistency
        return $this->getMessagesCursor($roomId, null, $limit, 'before');
    }

    /**
     * Get messages after a specific message (for real-time updates)
     * 
     * @param int $roomId
     * @param int $afterMessageId
     * @param int $limit
     * @return Collection
     */
    public function getMessagesAfter(int $roomId, int $afterMessageId, int $limit = 50): Collection
    {
        $afterMessage = ChatMessage::find($afterMessageId);
        if (!$afterMessage) {
            return collect();
        }

        $cursor = $this->generateCursor($afterMessage->id, $afterMessage->created_at);
        $result = $this->getMessagesCursor($roomId, $cursor, $limit, 'after');
        
        return $result['messages'];
    }

    /**
     * Search messages with cursor-based pagination
     * 
     * @param int $roomId
     * @param string $searchQuery
     * @param string|null $cursor
     * @param int $limit
     * @return array
     */
    public function searchMessages(
        int $roomId, 
        string $searchQuery, 
        ?string $cursor = null, 
        int $limit = 20
    ): array {
        $limit = min($limit, 50); // Lower limit for search results
        
        $query = ChatMessage::with(['user:id,name,email'])
            ->where('room_id', $roomId)
            ->where('message_type', 'text'); // Only search text messages

        // Use full-text search if available, otherwise LIKE
        if ($this->supportsFullTextSearch()) {
            $query->whereRaw('MATCH(message) AGAINST(? IN NATURAL LANGUAGE MODE)', [$searchQuery]);
        } else {
            $query->where('message', 'LIKE', '%' . $searchQuery . '%');
        }

        // Apply cursor if provided
        $cursorData = $this->parseCursor($cursor);
        if ($cursorData) {
            $query->where(function (Builder $q) use ($cursorData) {
                $q->where('created_at', '<', $cursorData['timestamp'])
                  ->orWhere(function (Builder $subQ) use ($cursorData) {
                      $subQ->where('created_at', '=', $cursorData['timestamp'])
                           ->where('id', '<', $cursorData['id']);
                  });
            });
        }

        $query->orderBy('created_at', 'desc')
              ->orderBy('id', 'desc');

        // Fetch one extra to determine if there are more results
        $messages = $query->limit($limit + 1)->get();
        
        $hasMore = $messages->count() > $limit;
        if ($hasMore) {
            $messages = $messages->slice(0, $limit);
        }

        $nextCursor = null;
        if ($hasMore && $messages->isNotEmpty()) {
            $lastMessage = $messages->last();
            $nextCursor = $this->generateCursor($lastMessage->id, $lastMessage->created_at);
        }

        return [
            'messages' => $messages->values(),
            'search_query' => $searchQuery,
            'pagination' => [
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'limit' => $limit,
                'count' => $messages->count()
            ]
        ];
    }

    /**
     * Get message statistics with efficient queries
     * 
     * @param int $roomId
     * @param int $days Number of days to analyze
     * @return array
     */
    public function getMessageStatistics(int $roomId, int $days = 7): array
    {
        $since = now()->subDays($days);
        
        // Use raw queries for better performance
        $stats = \DB::select("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_messages,
                COUNT(DISTINCT user_id) as active_users,
                COUNT(CASE WHEN message_type = 'text' THEN 1 END) as text_messages,
                COUNT(CASE WHEN message_type = 'system' THEN 1 END) as system_messages
            FROM chat_messages 
            WHERE room_id = ? AND created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ", [$roomId, $since]);

        // Get hourly distribution for the last 24 hours
        $hourlyStats = \DB::select("
            SELECT 
                HOUR(created_at) as hour,
                COUNT(*) as message_count
            FROM chat_messages 
            WHERE room_id = ? AND created_at >= ?
            GROUP BY HOUR(created_at)
            ORDER BY hour
        ", [$roomId, now()->subDay()]);

        // Get top users by message count
        $topUsers = \DB::select("
            SELECT 
                u.id, u.name, u.email,
                COUNT(cm.id) as message_count
            FROM chat_messages cm
            JOIN users u ON cm.user_id = u.id
            WHERE cm.room_id = ? AND cm.created_at >= ? AND cm.message_type = 'text'
            GROUP BY u.id, u.name, u.email
            ORDER BY message_count DESC
            LIMIT 10
        ", [$roomId, $since]);

        return [
            'period_days' => $days,
            'daily_stats' => $stats,
            'hourly_stats' => $hourlyStats,
            'top_users' => $topUsers,
            'generated_at' => now()
        ];
    }

    /**
     * Generate a cursor string from message ID and timestamp
     * 
     * @param int $messageId
     * @param \Carbon\Carbon $timestamp
     * @return string
     */
    private function generateCursor(int $messageId, $timestamp): string
    {
        $cursorData = [
            'id' => $messageId,
            'timestamp' => $timestamp->toISOString()
        ];
        
        return base64_encode(json_encode($cursorData));
    }

    /**
     * Parse cursor string to extract message ID and timestamp
     * 
     * @param string|null $cursor
     * @return array|null
     */
    private function parseCursor(?string $cursor): ?array
    {
        if (!$cursor) {
            return null;
        }

        try {
            $decoded = base64_decode($cursor);
            $data = json_decode($decoded, true);
            
            if (!isset($data['id']) || !isset($data['timestamp'])) {
                return null;
            }

            return [
                'id' => (int) $data['id'],
                'timestamp' => \Carbon\Carbon::parse($data['timestamp'])
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Check if database supports full-text search
     * 
     * @return bool
     */
    private function supportsFullTextSearch(): bool
    {
        try {
            // Check if we're using MySQL and the full-text index exists
            $driver = \DB::getDriverName();
            if ($driver !== 'mysql') {
                return false;
            }

            // Check if full-text index exists
            $indexes = \DB::select("SHOW INDEX FROM chat_messages WHERE Key_name = 'idx_message_fulltext'");
            return !empty($indexes);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Optimize message queries with proper indexing hints
     * 
     * @param Builder $query
     * @param int $roomId
     * @return Builder
     */
    private function optimizeQuery(Builder $query, int $roomId): Builder
    {
        // Add index hints for MySQL
        if (\DB::getDriverName() === 'mysql') {
            $query->from(\DB::raw('chat_messages USE INDEX (idx_room_id_cursor)'));
        }
        
        return $query;
    }
}