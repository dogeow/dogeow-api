<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\CreateRoomRequest;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use App\Models\ChatMessage;
use App\Events\Chat\MessageSent;
use App\Events\Chat\MessageDeleted;
use App\Services\ChatService;
use App\Services\ChatCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Carbon\Carbon;

class ChatController extends Controller
{
    protected ChatService $chatService;
    protected ChatCacheService $cacheService;

    public function __construct(ChatService $chatService, ChatCacheService $cacheService)
    {
        $this->chatService = $chatService;
        $this->cacheService = $cacheService;
    }
    /**
     * Get all available chat rooms.
     */
    public function getRooms(): JsonResponse
    {
        $rooms = $this->chatService->getActiveRooms();
        return response()->json($rooms);
    }

    /**
     * Create a new chat room.
     */
    public function createRoom(CreateRoomRequest $request): JsonResponse
    {
        $result = $this->chatService->createRoom([
            'name' => $request->name,
            'description' => $request->description,
        ], Auth::id());

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to create room',
                'errors' => $result['errors']
            ], 422);
        }

        return response()->json($result['room'], 201);
    }

    /**
     * Join a chat room.
     */
    public function joinRoom(Request $request, int $roomId): JsonResponse
    {
        $result = $this->chatService->joinRoom($roomId, Auth::id());

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to join room',
                'errors' => $result['errors']
            ], 422);
        }

        // Invalidate relevant caches
        $this->cacheService->invalidateOnlineUsers($roomId);
        $this->cacheService->invalidateRoomStats($roomId);
        $this->cacheService->invalidateRoomList();

        // Track room activity
        $this->cacheService->trackRoomActivity($roomId, 'user_joined', Auth::id());

        return response()->json([
            'message' => 'Successfully joined the room',
            'room' => $result['room'],
            'room_user' => $result['room_user'],
        ]);
    }

    /**
     * Leave a chat room.
     */
    public function leaveRoom(Request $request, int $roomId): JsonResponse
    {
        $result = $this->chatService->leaveRoom($roomId, Auth::id());

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to leave room',
                'errors' => $result['errors']
            ], 422);
        }

        // Invalidate relevant caches
        $this->cacheService->invalidateOnlineUsers($roomId);
        $this->cacheService->invalidateRoomStats($roomId);
        $this->cacheService->invalidateRoomList();

        // Track room activity
        $this->cacheService->trackRoomActivity($roomId, 'user_left', Auth::id());

        return response()->json([
            'message' => $result['message']
        ]);
    }

    /**
     * Delete a chat room (only by creator).
     */
    public function deleteRoom(Request $request, int $roomId): JsonResponse
    {
        $result = $this->chatService->deleteRoom($roomId, Auth::id());

        if (!$result['success']) {
            $statusCode = in_array('You do not have permission to delete this room', $result['errors']) ? 403 : 422;
            return response()->json([
                'message' => 'Failed to delete room',
                'errors' => $result['errors']
            ], $statusCode);
        }

        return response()->json([
            'message' => $result['message']
        ]);
    }

    /**
     * Get messages for a chat room with pagination.
     */
    public function getMessages(Request $request, int $roomId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        
        // Verify user is in the room
        $userInRoom = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', Auth::id())
            ->exists();

        if (!$userInRoom) {
            return response()->json([
                'message' => 'You must join the room to view messages'
            ], 403);
        }

        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);

        if ($page === 1) {
            // For the first page, get recent messages in chronological order
            $messages = $this->chatService->getRecentMessages($roomId, $perPage);
            return response()->json([
                'messages' => $messages,
                'pagination' => [
                    'current_page' => 1,
                    'has_more_pages' => $messages->count() === $perPage,
                ],
            ]);
        } else {
            // For subsequent pages, use pagination
            $paginatedMessages = $this->chatService->getMessageHistoryPaginated($roomId, $page, $perPage);
            return response()->json([
                'messages' => array_reverse($paginatedMessages->items()),
                'pagination' => [
                    'current_page' => $paginatedMessages->currentPage(),
                    'last_page' => $paginatedMessages->lastPage(),
                    'per_page' => $paginatedMessages->perPage(),
                    'total' => $paginatedMessages->total(),
                    'has_more_pages' => $paginatedMessages->hasMorePages(),
                ],
            ]);
        }
    }

    /**
     * Send a message to a chat room.
     */
    public function sendMessage(SendMessageRequest $request, int $roomId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $userId = Auth::id();

        // Verify user is in the room and online
        $roomUser = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->where('is_online', true)
            ->first();

        if (!$roomUser) {
            return response()->json([
                'message' => 'You must be online in the room to send messages'
            ], 403);
        }

        // Check if user is muted or banned
        if (!$roomUser->canSendMessages()) {
            if ($roomUser->isBanned()) {
                $banMessage = 'You are banned from this room';
                if ($roomUser->banned_until) {
                    $banMessage .= ' until ' . $roomUser->banned_until->format('Y-m-d H:i:s');
                }
                return response()->json(['message' => $banMessage], 403);
            }

            if ($roomUser->isMuted()) {
                $muteMessage = 'You are muted in this room';
                if ($roomUser->muted_until) {
                    $muteMessage .= ' until ' . $roomUser->muted_until->format('Y-m-d H:i:s');
                }
                return response()->json(['message' => $muteMessage], 403);
            }
        }

        // Enhanced rate limiting using Redis
        $rateLimitKey = "send_message:{$userId}:{$roomId}";
        $rateLimitResult = $this->cacheService->checkRateLimit($rateLimitKey, 10, 60); // 10 messages per minute
        
        if (!$rateLimitResult['allowed']) {
            $resetTime = $rateLimitResult['reset_time']->diffInSeconds(now());
            return response()->json([
                'message' => "Too many messages. Please wait {$resetTime} seconds before sending another message.",
                'rate_limit' => [
                    'attempts' => $rateLimitResult['attempts'],
                    'remaining' => $rateLimitResult['remaining'],
                    'reset_time' => $rateLimitResult['reset_time']->toISOString()
                ]
            ], 429);
        }

        // Process the message using ChatService
        $result = $this->chatService->processMessage(
            $roomId,
            $userId,
            $request->message,
            $request->message_type ?? ChatMessage::TYPE_TEXT
        );

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to send message',
                'errors' => $result['errors']
            ], 422);
        }

        // Update user's last seen timestamp
        $roomUser->updateLastSeen();

        // Invalidate relevant caches
        $this->cacheService->invalidateMessageHistory($roomId);
        $this->cacheService->invalidateRoomStats($roomId);
        $this->cacheService->invalidateRoomList();

        // Track room activity
        $this->cacheService->trackRoomActivity($roomId, 'message_sent', Auth::id());

        // Broadcast the message to all users in the room
        broadcast(new MessageSent($result['message']));

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => [
                'id' => $result['message']->id,
                'room_id' => $result['message']->room_id,
                'user_id' => $result['message']->user_id,
                'message' => $result['message']->message,
                'message_type' => $result['message']->message_type,
                'created_at' => $result['message']->created_at->toISOString(),
                'user' => [
                    'id' => $result['message']->user->id,
                    'name' => $result['message']->user->name,
                    'email' => $result['message']->user->email,
                ],
                'mentions' => $result['mentions'],
            ],
        ], 201);
    }

    /**
     * Delete a message (for moderation).
     */
    public function deleteMessage(Request $request, int $roomId, int $messageId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $message = ChatMessage::where('room_id', $roomId)->findOrFail($messageId);
        $userId = Auth::id();

        // Check if user can delete the message
        // User can delete if they are the message author or room creator
        $canDelete = $message->user_id === $userId || $room->created_by === $userId;

        if (!$canDelete) {
            return response()->json([
                'message' => 'You are not authorized to delete this message'
            ], 403);
        }

        try {
            DB::beginTransaction();

            // Store message info before deletion for broadcasting
            $messageId = $message->id;
            $roomId = $message->room_id;

            // Delete the message
            $message->delete();

            DB::commit();

            // Broadcast the deletion
            broadcast(new MessageDeleted($messageId, $roomId, $userId));

            return response()->json([
                'message' => 'Message deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get online users in a chat room.
     */
    public function getOnlineUsers(Request $request, int $roomId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        
        // Verify user is in the room
        $userInRoom = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', Auth::id())
            ->exists();

        if (!$userInRoom) {
            return response()->json([
                'message' => 'You must join the room to view online users'
            ], 403);
        }

        $onlineUsers = $this->chatService->getOnlineUsers($roomId);

        return response()->json([
            'online_users' => $onlineUsers,
            'count' => $onlineUsers->count(),
        ]);
    }

    /**
     * Update user status (heartbeat/presence tracking).
     */
    public function updateUserStatus(Request $request, int $roomId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        
        $result = $this->chatService->processHeartbeat($roomId, Auth::id());

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to update status',
                'errors' => $result['errors']
            ], 404);
        }

        return response()->json([
            'message' => 'Status updated successfully',
            'last_seen_at' => $result['last_seen_at'],
        ]);
    }

    /**
     * Cleanup disconnected users (called by scheduled task or manually).
     */
    public function cleanupDisconnectedUsers(Request $request): JsonResponse
    {
        $result = $this->chatService->cleanupInactiveUsers();

        if (!$result['success']) {
            return response()->json([
                'message' => 'Failed to cleanup disconnected users',
                'errors' => $result['errors']
            ], 500);
        }

        return response()->json([
            'message' => $result['message'],
            'cleaned_users_count' => $result['cleaned_users'],
        ]);
    }

    /**
     * Get user's presence status in a specific room.
     */
    public function getUserPresenceStatus(Request $request, int $roomId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $userId = Auth::id();

        $roomUser = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$roomUser) {
            return response()->json([
                'message' => 'You are not in this room',
                'is_in_room' => false,
                'is_online' => false,
            ]);
        }

        return response()->json([
            'is_in_room' => true,
            'is_online' => $roomUser->is_online,
            'joined_at' => $roomUser->joined_at,
            'last_seen_at' => $roomUser->last_seen_at,
            'is_inactive' => $roomUser->isInactive(),
        ]);
    }
}