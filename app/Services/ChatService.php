<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChatService
{
    protected ChatCacheService $cacheService;
    protected ChatPaginationService $paginationService;

    public function __construct(
        ChatCacheService $cacheService,
        ChatPaginationService $paginationService
    ) {
        $this->cacheService = $cacheService;
        $this->paginationService = $paginationService;
    }
    /**
     * Message validation rules
     */
    const MAX_MESSAGE_LENGTH = 1000;
    const MIN_MESSAGE_LENGTH = 1;
    
    /**
     * Pagination settings
     */
    const DEFAULT_PAGE_SIZE = 50;
    const MAX_PAGE_SIZE = 100;
    
    /**
     * Room validation rules
     */
    const MAX_ROOM_NAME_LENGTH = 100;
    const MIN_ROOM_NAME_LENGTH = 3;
    const MAX_ROOM_DESCRIPTION_LENGTH = 500;

    /**
     * Validate and sanitize a message before processing
     *
     * @param string $message
     * @return array
     */
    public function validateMessage(string $message): array
    {
        $errors = [];
        
        // Trim whitespace
        $message = trim($message);
        
        // Check length constraints
        if (strlen($message) < self::MIN_MESSAGE_LENGTH) {
            $errors[] = 'Message cannot be empty';
        }
        
        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $errors[] = 'Message cannot exceed ' . self::MAX_MESSAGE_LENGTH . ' characters';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_message' => $this->sanitizeMessage($message)
        ];
    }

    /**
     * Sanitize message content to prevent XSS and other security issues
     *
     * @param string $message
     * @return string
     */
    public function sanitizeMessage(string $message): string
    {
        // Remove any HTML tags
        $message = strip_tags($message);
        
        // Convert special characters to HTML entities
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        // Normalize whitespace
        $message = preg_replace('/\s+/', ' ', $message);
        
        return trim($message);
    }

    /**
     * Detect and process user mentions in a message
     *
     * @param string $message
     * @return array
     */
    public function processMentions(string $message): array
    {
        $mentions = [];
        
        // Pattern to match @username mentions
        $pattern = '/@([a-zA-Z0-9_.-]+)/';
        
        if (preg_match_all($pattern, $message, $matches)) {
            $usernames = $matches[1];
            
            // Find users by name (case-insensitive)
            $users = User::whereIn(DB::raw('LOWER(name)'), array_map('strtolower', $usernames))
                ->get(['id', 'name', 'email']);
            
            foreach ($users as $user) {
                $mentions[] = [
                    'user_id' => $user->id,
                    'username' => $user->name,
                    'email' => $user->email
                ];
            }
        }
        
        return $mentions;
    }

    /**
     * Format message with emoji support and mention highlighting
     *
     * @param string $message
     * @param array $mentions
     * @return string
     */
    public function formatMessage(string $message, array $mentions = []): string
    {
        // Process mentions - wrap them in special markup for frontend highlighting
        foreach ($mentions as $mention) {
            $pattern = '/@' . preg_quote($mention['username'], '/') . '/i';
            $replacement = '<mention data-user-id="' . $mention['user_id'] . '">@' . $mention['username'] . '</mention>';
            $message = preg_replace($pattern, $replacement, $message);
        }
        
        // Convert common text emoticons to emoji
        $emoticons = [
            ':)' => '😊',
            ':(' => '😢',
            ':D' => '😃',
            ':P' => '😛',
            ':o' => '😮',
            ';)' => '😉',
            '<3' => '❤️',
            '</3' => '💔',
            ':thumbsup:' => '👍',
            ':thumbsdown:' => '👎',
            ':fire:' => '🔥',
            ':star:' => '⭐',
            ':check:' => '✅',
            ':x:' => '❌'
        ];
        
        foreach ($emoticons as $text => $emoji) {
            $message = str_replace($text, $emoji, $message);
        }
        
        return $message;
    }

    /**
     * Get paginated message history for a room using cursor-based pagination
     *
     * @param int $roomId
     * @param string|null $cursor
     * @param int $limit
     * @param string $direction
     * @return array
     */
    public function getMessageHistory(int $roomId, ?string $cursor = null, int $limit = self::DEFAULT_PAGE_SIZE, string $direction = 'before'): array
    {
        return $this->paginationService->getMessagesCursor($roomId, $cursor, $limit, $direction);
    }

    /**
     * Get recent messages for a room (for initial load) with caching
     *
     * @param int $roomId
     * @param int $limit
     * @return Collection
     */
    public function getRecentMessages(int $roomId, int $limit = self::DEFAULT_PAGE_SIZE): Collection
    {
        $result = $this->paginationService->getRecentMessages($roomId, $limit);
        return $result['messages'];
    }

    /**
     * Get paginated message history for backward compatibility
     *
     * @param int $roomId
     * @param int $page
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getMessageHistoryPaginated(int $roomId, int $page = 1, int $perPage = self::DEFAULT_PAGE_SIZE): LengthAwarePaginator
    {
        // Ensure page size doesn't exceed maximum
        $perPage = min($perPage, self::MAX_PAGE_SIZE);
        
        return ChatMessage::with(['user:id,name,email'])
            ->forRoom($roomId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Process and create a new message
     *
     * @param int $roomId
     * @param int $userId
     * @param string $message
     * @param string $messageType
     * @return array
     */
    public function processMessage(int $roomId, int $userId, string $message, string $messageType = ChatMessage::TYPE_TEXT): array
    {
        // Validate the message
        $validation = $this->validateMessage($message);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        $sanitizedMessage = $validation['sanitized_message'];
        
        // Apply content filtering for text messages
        if ($messageType === ChatMessage::TYPE_TEXT) {
            $contentFilterService = app(ContentFilterService::class);
            $filterResult = $contentFilterService->processMessage($sanitizedMessage, $userId, $roomId);
            
            if (!$filterResult['allowed']) {
                return [
                    'success' => false,
                    'errors' => ['Message blocked by content filter'],
                    'filter_result' => $filterResult,
                    'blocked' => true
                ];
            }
            
            // Use filtered message if content was modified
            $sanitizedMessage = $filterResult['filtered_message'];
        }
        
        // Process mentions
        $mentions = $this->processMentions($sanitizedMessage);
        
        // Format the message
        $formattedMessage = $this->formatMessage($sanitizedMessage, $mentions);
        
        try {
            // Create the message
            $chatMessage = ChatMessage::create([
                'room_id' => $roomId,
                'user_id' => $userId,
                'message' => $formattedMessage,
                'message_type' => $messageType
            ]);
            
            // Load the user relationship
            $chatMessage->load('user:id,name,email');
            
            $result = [
                'success' => true,
                'message' => $chatMessage,
                'mentions' => $mentions,
                'original_message' => $sanitizedMessage
            ];
            
            // Include filter information if filtering was applied
            if ($messageType === ChatMessage::TYPE_TEXT && isset($filterResult)) {
                $result['filter_result'] = $filterResult;
            }
            
            return $result;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Failed to save message: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Create a system message
     * Note: Since the database requires user_id, we'll use a system user (user ID 1) for system messages
     *
     * @param int $roomId
     * @param string $message
     * @param int $systemUserId
     * @return ChatMessage|null
     */
    public function createSystemMessage(int $roomId, string $message, int $systemUserId = 1): ?ChatMessage
    {
        try {
            return ChatMessage::create([
                'room_id' => $roomId,
                'user_id' => $systemUserId, // Use system user ID
                'message' => $this->sanitizeMessage($message),
                'message_type' => ChatMessage::TYPE_SYSTEM
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Search messages in a room with cursor-based pagination
     *
     * @param int $roomId
     * @param string $query
     * @param string|null $cursor
     * @param int $limit
     * @return array
     */
    public function searchMessages(int $roomId, string $query, ?string $cursor = null, int $limit = 20): array
    {
        $sanitizedQuery = $this->sanitizeMessage($query);
        return $this->paginationService->searchMessages($roomId, $sanitizedQuery, $cursor, $limit);
    }

    /**
     * Get message statistics for a room
     *
     * @param int $roomId
     * @return array
     */
    public function getMessageStats(int $roomId): array
    {
        $totalMessages = ChatMessage::forRoom($roomId)->count();
        $textMessages = ChatMessage::forRoom($roomId)->textMessages()->count();
        $systemMessages = ChatMessage::forRoom($roomId)->systemMessages()->count();
        
        $topUsers = ChatMessage::forRoom($roomId)
            ->where('message_type', ChatMessage::TYPE_TEXT)
            ->select('user_id', DB::raw('COUNT(*) as message_count'))
            ->with('user:id,name')
            ->groupBy('user_id')
            ->orderBy('message_count', 'desc')
            ->limit(5)
            ->get();
        
        return [
            'total_messages' => $totalMessages,
            'text_messages' => $textMessages,
            'system_messages' => $systemMessages,
            'top_users' => $topUsers
        ];
    }

    // ========================================
    // ROOM MANAGEMENT METHODS
    // ========================================

    /**
     * Validate room creation data
     *
     * @param array $data
     * @return array
     */
    public function validateRoomData(array $data): array
    {
        $errors = [];
        
        // Validate room name
        if (empty($data['name'])) {
            $errors[] = 'Room name is required';
        } else {
            $name = trim($data['name']);
            if (strlen($name) < self::MIN_ROOM_NAME_LENGTH) {
                $errors[] = 'Room name must be at least ' . self::MIN_ROOM_NAME_LENGTH . ' characters';
            }
            if (strlen($name) > self::MAX_ROOM_NAME_LENGTH) {
                $errors[] = 'Room name cannot exceed ' . self::MAX_ROOM_NAME_LENGTH . ' characters';
            }
            
            // Check for duplicate room names
            if (ChatRoom::where('name', $name)->where('is_active', true)->exists()) {
                $errors[] = 'A room with this name already exists';
            }
        }
        
        // Validate description if provided
        if (!empty($data['description'])) {
            $description = trim($data['description']);
            if (strlen($description) > self::MAX_ROOM_DESCRIPTION_LENGTH) {
                $errors[] = 'Room description cannot exceed ' . self::MAX_ROOM_DESCRIPTION_LENGTH . ' characters';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_data' => [
                'name' => isset($name) ? $this->sanitizeMessage($name) : '',
                'description' => isset($description) ? $this->sanitizeMessage($description) : null
            ]
        ];
    }

    /**
     * Create a new chat room
     *
     * @param array $data
     * @param int $createdBy
     * @return array
     */
    public function createRoom(array $data, int $createdBy): array
    {
        $validation = $this->validateRoomData($data);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        try {
            DB::beginTransaction();
            
            $room = ChatRoom::create([
                'name' => $validation['sanitized_data']['name'],
                'description' => $validation['sanitized_data']['description'],
                'created_by' => $createdBy,
                'is_active' => true
            ]);
            
            // Automatically join the creator to the room
            ChatRoomUser::create([
                'room_id' => $room->id,
                'user_id' => $createdBy,
                'joined_at' => now(),
                'is_online' => true
            ]);
            
            // Create a system message for room creation
            $this->createSystemMessage($room->id, "Room '{$room->name}' has been created", $createdBy);
            
            DB::commit();
            
            return [
                'success' => true,
                'room' => $room->load('creator:id,name,email')
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'errors' => ['Failed to create room: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Check if user has permission to perform room operations
     *
     * @param int $roomId
     * @param int $userId
     * @param string $operation
     * @return bool
     */
    public function checkRoomPermission(int $roomId, int $userId, string $operation): bool
    {
        $room = ChatRoom::find($roomId);
        
        if (!$room || !$room->is_active) {
            return false;
        }
        
        $user = User::find($userId);
        if (!$user) {
            return false;
        }
        
        switch ($operation) {
            case 'delete':
            case 'edit':
                // Only room creator or admin can delete/edit room
                return $room->created_by === $userId || $user->hasRole('admin');
                
            case 'join':
                // Any authenticated user can join active rooms
                return true;
                
            case 'moderate':
                // Room creator or admin can moderate
                return $room->created_by === $userId || $user->hasRole('admin');
                
            default:
                return false;
        }
    }

    /**
     * Delete a chat room with safety checks
     *
     * @param int $roomId
     * @param int $userId
     * @return array
     */
    public function deleteRoom(int $roomId, int $userId): array
    {
        if (!$this->checkRoomPermission($roomId, $userId, 'delete')) {
            return [
                'success' => false,
                'errors' => ['You do not have permission to delete this room']
            ];
        }
        
        $room = ChatRoom::find($roomId);
        
        // Check if room has active users (excluding the creator)
        $activeUsers = ChatRoomUser::where('room_id', $roomId)
            ->where('is_online', true)
            ->where('user_id', '!=', $userId)
            ->count();
        
        if ($activeUsers > 0) {
            return [
                'success' => false,
                'errors' => ['Cannot delete room with active users. Please wait for all users to leave.']
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // Create system message before deletion
            $this->createSystemMessage($roomId, "Room '{$room->name}' is being deleted", $userId);
            
            // Soft delete by marking as inactive instead of hard delete to preserve message history
            $room->update(['is_active' => false]);
            
            // Remove all user associations
            ChatRoomUser::where('room_id', $roomId)->delete();
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Room deleted successfully'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'errors' => ['Failed to delete room: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Update room information
     *
     * @param int $roomId
     * @param array $data
     * @param int $userId
     * @return array
     */
    public function updateRoom(int $roomId, array $data, int $userId): array
    {
        if (!$this->checkRoomPermission($roomId, $userId, 'edit')) {
            return [
                'success' => false,
                'errors' => ['You do not have permission to edit this room']
            ];
        }
        
        $room = ChatRoom::find($roomId);
        
        // Validate the new data
        $validation = $this->validateRoomData($data);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        try {
            $oldName = $room->name;
            
            $room->update([
                'name' => $validation['sanitized_data']['name'],
                'description' => $validation['sanitized_data']['description']
            ]);
            
            // Create system message if name changed
            if ($oldName !== $room->name) {
                $this->createSystemMessage($roomId, "Room renamed from '{$oldName}' to '{$room->name}'", $userId);
            }
            
            return [
                'success' => true,
                'room' => $room->fresh()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Failed to update room: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Get room statistics and analytics
     *
     * @param int $roomId
     * @return array
     */
    public function getRoomStats(int $roomId): array
    {
        $room = ChatRoom::with('creator:id,name,email')->find($roomId);
        
        if (!$room) {
            return [];
        }
        
        $totalUsers = ChatRoomUser::where('room_id', $roomId)->count();
        $onlineUsers = ChatRoomUser::where('room_id', $roomId)->where('is_online', true)->count();
        $messageStats = $this->getMessageStats($roomId);
        
        $recentActivity = ChatMessage::forRoom($roomId)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        
        $peakHours = ChatMessage::forRoom($roomId)
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('count', 'desc')
            ->limit(3)
            ->get();
        
        return [
            'room' => $room,
            'total_users' => $totalUsers,
            'online_users' => $onlineUsers,
            'messages' => $messageStats,
            'recent_activity_24h' => $recentActivity,
            'peak_hours' => $peakHours,
            'created_at' => $room->created_at,
            'last_activity' => ChatMessage::forRoom($roomId)->latest()->first()?->created_at
        ];
    }

    /**
     * Get list of active rooms with basic stats (cached)
     *
     * @return Collection
     */
    public function getActiveRooms(): Collection
    {
        return $this->cacheService->getRoomList();
    }

    // ========================================
    // USER PRESENCE METHODS
    // ========================================

    /**
     * Presence timeout settings
     */
    const PRESENCE_TIMEOUT_MINUTES = 5;
    const HEARTBEAT_INTERVAL_SECONDS = 30;

    /**
     * Update user's online status in a room
     *
     * @param int $roomId
     * @param int $userId
     * @param bool $isOnline
     * @return array
     */
    public function updateUserStatus(int $roomId, int $userId, bool $isOnline = true): array
    {
        try {
            $roomUser = ChatRoomUser::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();
            
            if (!$roomUser) {
                // User not in room, create entry if going online
                if ($isOnline) {
                    $roomUser = ChatRoomUser::create([
                        'room_id' => $roomId,
                        'user_id' => $userId,
                        'joined_at' => now(),
                        'last_seen_at' => now(),
                        'is_online' => true
                    ]);
                } else {
                    return [
                        'success' => false,
                        'errors' => ['User not found in room']
                    ];
                }
            } else {
                // Update existing entry
                $roomUser->update([
                    'is_online' => $isOnline,
                    'last_seen_at' => now()
                ]);
            }
            
            return [
                'success' => true,
                'room_user' => $roomUser->load('user:id,name,email'),
                'status_changed' => true
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Failed to update user status: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Process user joining a room
     *
     * @param int $roomId
     * @param int $userId
     * @return array
     */
    public function joinRoom(int $roomId, int $userId): array
    {
        $room = ChatRoom::find($roomId);
        
        if (!$room || !$room->is_active) {
            return [
                'success' => false,
                'errors' => ['Room not found or inactive']
            ];
        }
        
        try {
            DB::beginTransaction();
            
            // Update or create user presence
            $result = $this->updateUserStatus($roomId, $userId, true);
            
            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }
            
            // Create system message for user joining
            $user = User::find($userId);
            $this->createSystemMessage($roomId, "{$user->name} joined the room", $userId);
            
            // Broadcast user joined event
            broadcast(new \App\Events\Chat\UserJoined($user, $roomId));
            
            DB::commit();
            
            return [
                'success' => true,
                'room_user' => $result['room_user'],
                'room' => $room
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'errors' => ['Failed to join room: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Process user leaving a room
     *
     * @param int $roomId
     * @param int $userId
     * @return array
     */
    public function leaveRoom(int $roomId, int $userId): array
    {
        try {
            DB::beginTransaction();
            
            // Update user status to offline
            $result = $this->updateUserStatus($roomId, $userId, false);
            
            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }
            
            // Create system message for user leaving
            $user = User::find($userId);
            $this->createSystemMessage($roomId, "{$user->name} left the room", $userId);
            
            // Broadcast user left event
            broadcast(new \App\Events\Chat\UserLeft($user, $roomId));
            
            DB::commit();
            
            return [
                'success' => true,
                'message' => 'Successfully left the room'
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'errors' => ['Failed to leave room: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Get online users for a room (cached)
     *
     * @param int $roomId
     * @return Collection
     */
    public function getOnlineUsers(int $roomId): Collection
    {
        return $this->cacheService->getOnlineUsers($roomId);
    }

    /**
     * Process heartbeat to keep user online
     *
     * @param int $roomId
     * @param int $userId
     * @return array
     */
    public function processHeartbeat(int $roomId, int $userId): array
    {
        try {
            $roomUser = ChatRoomUser::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();
            
            if (!$roomUser) {
                return [
                    'success' => false,
                    'errors' => ['User not found in room']
                ];
            }
            
            $roomUser->update([
                'last_seen_at' => now(),
                'is_online' => true
            ]);
            
            return [
                'success' => true,
                'last_seen_at' => $roomUser->last_seen_at
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Failed to process heartbeat: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Clean up inactive users (mark as offline)
     *
     * @return array
     */
    public function cleanupInactiveUsers(): array
    {
        try {
            $timeoutThreshold = now()->subMinutes(self::PRESENCE_TIMEOUT_MINUTES);
            
            $inactiveUsers = ChatRoomUser::where('is_online', true)
                ->where('last_seen_at', '<', $timeoutThreshold)
                ->get();
            
            $cleanedCount = 0;
            
            foreach ($inactiveUsers as $roomUser) {
                $roomUser->update(['is_online' => false]);
                
                // Create system message for user going offline due to inactivity
                $user = User::find($roomUser->user_id);
                if ($user) {
                    $this->createSystemMessage(
                        $roomUser->room_id, 
                        "{$user->name} went offline due to inactivity", 
                        $roomUser->user_id
                    );
                }
                
                $cleanedCount++;
            }
            
            return [
                'success' => true,
                'cleaned_users' => $cleanedCount,
                'message' => "Cleaned up {$cleanedCount} inactive users"
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'errors' => ['Failed to cleanup inactive users: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Get user activity tracking for a room
     *
     * @param int $roomId
     * @param int $hours
     * @return array
     */
    public function getUserActivity(int $roomId, int $hours = 24): array
    {
        $since = now()->subHours($hours);
        
        // Get users who were active in the time period
        $activeUsers = ChatRoomUser::where('room_id', $roomId)
            ->where('last_seen_at', '>=', $since)
            ->with('user:id,name,email')
            ->get();
        
        // Get message activity by user
        $messageActivity = ChatMessage::forRoom($roomId)
            ->where('created_at', '>=', $since)
            ->select('user_id', DB::raw('COUNT(*) as message_count'))
            ->groupBy('user_id')
            ->with('user:id,name,email')
            ->get();
        
        // Get join/leave activity
        $joinLeaveActivity = ChatMessage::forRoom($roomId)
            ->where('message_type', ChatMessage::TYPE_SYSTEM)
            ->where('created_at', '>=', $since)
            ->where(function ($query) {
                $query->where('message', 'LIKE', '%joined the room%')
                      ->orWhere('message', 'LIKE', '%left the room%');
            })
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
        
        return [
            'period_hours' => $hours,
            'active_users' => $activeUsers->map(function ($roomUser) {
                return [
                    'user' => $roomUser->user,
                    'last_seen_at' => $roomUser->last_seen_at,
                    'is_online' => $roomUser->is_online,
                    'joined_at' => $roomUser->joined_at
                ];
            }),
            'message_activity' => $messageActivity,
            'join_leave_activity' => $joinLeaveActivity,
            'total_active_users' => $activeUsers->count(),
            'currently_online' => $activeUsers->where('is_online', true)->count()
        ];
    }

    /**
     * Get presence statistics for all rooms
     *
     * @return array
     */
    public function getPresenceStats(): array
    {
        $totalOnlineUsers = ChatRoomUser::where('is_online', true)->count();
        $totalRoomsWithUsers = ChatRoomUser::where('is_online', true)
            ->distinct('room_id')
            ->count();
        
        $roomActivity = ChatRoom::where('is_active', true)
            ->withCount([
                'users as online_count' => function ($query) {
                    $query->where('is_online', true);
                }
            ])
            ->orderBy('online_count', 'desc')
            ->get(['id', 'name', 'online_count']);
        
        return [
            'total_online_users' => $totalOnlineUsers,
            'active_rooms' => $totalRoomsWithUsers,
            'room_activity' => $roomActivity,
            'last_updated' => now()
        ];
    }
}