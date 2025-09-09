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
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ChatController extends Controller
{
    protected ChatService $chatService;
    protected ChatCacheService $cacheService;

    // 常量定义
    private const DEFAULT_PAGE_SIZE = 50;
    private const MAX_PAGE_SIZE = 100;
    private const RATE_LIMIT_MESSAGES_PER_MINUTE = 10;
    private const RATE_LIMIT_WINDOW_SECONDS = 60;

    public function __construct(ChatService $chatService, ChatCacheService $cacheService)
    {
        $this->chatService = $chatService;
        $this->cacheService = $cacheService;
    }

    /**
     * 获取当前认证用户ID
     */
    private function getCurrentUserId(): int
    {
        return Auth::id();
    }

    /**
     * 验证用户是否在房间中
     */
    private function validateUserInRoom(int $roomId, int $userId): bool
    {
        return ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 获取用户在房间中的信息
     */
    private function getUserInRoom(int $roomId, int $userId): ?ChatRoomUser
    {
        return ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * 验证分页参数
     */
    private function validatePaginationParams(Request $request): array
    {
        $perPage = min(
            max((int) $request->get('per_page', self::DEFAULT_PAGE_SIZE), 1),
            self::MAX_PAGE_SIZE
        );
        $page = max((int) $request->get('page', 1), 1);

        return [$page, $perPage];
    }

    /**
     * 统一错误响应格式
     */
    private function errorResponse(string $message, array $errors = [], int $statusCode = 422): JsonResponse
    {
        $response = ['message' => $message];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * 统一成功响应格式
     */
    private function successResponse(array $data = [], string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        $response = ['message' => $message];
        
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }

        return response()->json($response, $statusCode);
    }

    /**
     * 使相关缓存失效
     */
    private function invalidateRelatedCaches(int $roomId): void
    {
        $this->cacheService->invalidateOnlineUsers($roomId);
        $this->cacheService->invalidateRoomStats($roomId);
        $this->cacheService->invalidateRoomList();
    }

    /**
     * 记录房间活动
     */
    private function trackRoomActivity(int $roomId, string $action, int $userId): void
    {
        $this->cacheService->trackRoomActivity($roomId, $action, $userId);
    }
    /**
     * Get all available chat rooms.
     */
    public function getRooms(): JsonResponse
    {
        try {
            $rooms = $this->chatService->getActiveRooms();
            return $this->successResponse(['rooms' => $rooms], 'Rooms retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve rooms', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to retrieve rooms', [], 500);
        }
    }

    /**
     * Create a new chat room.
     */
    public function createRoom(CreateRoomRequest $request): JsonResponse
    {
        try {
            $result = $this->chatService->createRoom([
                'name' => $request->name,
                'description' => $request->description,
            ], $this->getCurrentUserId());

            if (!$result['success']) {
                return $this->errorResponse('Failed to create room', $result['errors']);
            }

            Log::info('Room created successfully', [
                'room_id' => $result['room']->id,
                'room_name' => $result['room']->name,
                'created_by' => $this->getCurrentUserId()
            ]);

            return $this->successResponse(['room' => $result['room']], 'Room created successfully', 201);
        } catch (\Exception $e) {
            Log::error('Failed to create room', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId(),
                'room_name' => $request->name ?? 'unknown'
            ]);
            return $this->errorResponse('Failed to create room', [], 500);
        }
    }

    /**
     * Join a chat room.
     */
    public function joinRoom(Request $request, int $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $result = $this->chatService->joinRoom($roomId, $userId);

            if (!$result['success']) {
                return $this->errorResponse('Failed to join room', $result['errors']);
            }

            // 使相关缓存失效
            $this->invalidateRelatedCaches($roomId);

            // 记录房间活动
            $this->trackRoomActivity($roomId, 'user_joined', $userId);

            Log::info('User joined room', [
                'room_id' => $roomId,
                'user_id' => $userId
            ]);

            return $this->successResponse([
                'room' => $result['room'],
                'room_user' => $result['room_user'],
            ], 'Successfully joined the room');
        } catch (\Exception $e) {
            Log::error('Failed to join room', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to join room', [], 500);
        }
    }

    /**
     * Leave a chat room.
     */
    public function leaveRoom(Request $request, int $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $result = $this->chatService->leaveRoom($roomId, $userId);

            if (!$result['success']) {
                return $this->errorResponse('Failed to leave room', $result['errors']);
            }

            // 使相关缓存失效
            $this->invalidateRelatedCaches($roomId);

            // 记录房间活动
            $this->trackRoomActivity($roomId, 'user_left', $userId);

            Log::info('User left room', [
                'room_id' => $roomId,
                'user_id' => $userId
            ]);

            return $this->successResponse([], $result['message']);
        } catch (\Exception $e) {
            Log::error('Failed to leave room', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to leave room', [], 500);
        }
    }

    /**
     * Delete a chat room (only by creator).
     */
    public function deleteRoom(Request $request, int $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $result = $this->chatService->deleteRoom($roomId, $userId);

            if (!$result['success']) {
                $statusCode = in_array('You do not have permission to delete this room', $result['errors']) ? 403 : 422;
                return $this->errorResponse('Failed to delete room', $result['errors'], $statusCode);
            }

            Log::info('Room deleted successfully', [
                'room_id' => $roomId,
                'deleted_by' => $userId
            ]);

            return $this->successResponse([], $result['message']);
        } catch (\Exception $e) {
            Log::error('Failed to delete room', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to delete room', [], 500);
        }
    }

    /**
     * Get messages for a chat room with pagination.
     */
    public function getMessages(Request $request, int $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // 验证房间是否存在
            $room = ChatRoom::active()->findOrFail($roomId);
            
            // 验证用户是否在房间中
            if (!$this->validateUserInRoom($roomId, $userId)) {
                return $this->errorResponse('You must join the room to view messages', [], 403);
            }

            [$page, $perPage] = $this->validatePaginationParams($request);

            if ($page === 1) {
                // 第一页获取最近的消息，按时间顺序排列
                $messages = $this->chatService->getRecentMessages($roomId, $perPage);
                return $this->successResponse([
                    'messages' => $messages,
                    'pagination' => [
                        'current_page' => 1,
                        'has_more_pages' => $messages->count() === $perPage,
                    ],
                ], 'Messages retrieved successfully');
            } else {
                // 后续页面使用分页
                $paginatedMessages = $this->chatService->getMessageHistoryPaginated($roomId, $page, $perPage);
                return $this->successResponse([
                    'messages' => array_reverse($paginatedMessages->items()),
                    'pagination' => [
                        'current_page' => $paginatedMessages->currentPage(),
                        'last_page' => $paginatedMessages->lastPage(),
                        'per_page' => $paginatedMessages->perPage(),
                        'total' => $paginatedMessages->total(),
                        'has_more_pages' => $paginatedMessages->hasMorePages(),
                    ],
                ], 'Messages retrieved successfully');
            }
        } catch (\Exception $e) {
            Log::error('Failed to retrieve messages', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to retrieve messages', [], 500);
        }
    }

    /**
     * Send a message to a chat room.
     */
    public function sendMessage(SendMessageRequest $request, int $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // 验证房间是否存在
            $room = ChatRoom::active()->findOrFail($roomId);

            // 验证用户是否在房间中且在线
            $roomUser = $this->getUserInRoom($roomId, $userId);
            if (!$roomUser || !$roomUser->is_online) {
                return $this->errorResponse('You must be online in the room to send messages', [], 403);
            }

            // 检查用户是否被禁言或封禁
            $permissionCheck = $this->checkUserPermissions($roomUser);
            if (!$permissionCheck['allowed']) {
                return $this->errorResponse($permissionCheck['message'], [], 403);
            }

            // 速率限制检查
            $rateLimitCheck = $this->checkRateLimit($userId, $roomId);
            if (!$rateLimitCheck['allowed']) {
                return $this->errorResponse($rateLimitCheck['message'], $rateLimitCheck['data'] ?? [], 429);
            }

            // 处理消息
            $result = $this->chatService->processMessage(
                $roomId,
                $userId,
                $request->message,
                $request->message_type ?? ChatMessage::TYPE_TEXT
            );

            if (!$result['success']) {
                return $this->errorResponse('Failed to send message', $result['errors']);
            }

            // 更新用户最后活跃时间
            $roomUser->updateLastSeen();

            // 使相关缓存失效
            $this->invalidateRelatedCaches($roomId);
            $this->cacheService->invalidateMessageHistory($roomId);

            // 记录房间活动
            $this->trackRoomActivity($roomId, 'message_sent', $userId);

            // 广播消息
            broadcast(new MessageSent($result['message']));

            Log::info('Message sent successfully', [
                'message_id' => $result['message']->id,
                'room_id' => $roomId,
                'user_id' => $userId,
                'message_type' => $result['message']->message_type
            ]);

            return $this->successResponse([
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
            ], 'Message sent successfully', 201);
        } catch (\Exception $e) {
            Log::error('Failed to send message', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to send message', [], 500);
        }
    }

    /**
     * 检查用户权限（禁言/封禁状态）
     */
    private function checkUserPermissions(ChatRoomUser $roomUser): array
    {
        if (!$roomUser->canSendMessages()) {
            if ($roomUser->isBanned()) {
                $banMessage = 'You are banned from this room';
                if ($roomUser->banned_until) {
                    $banMessage .= ' until ' . $roomUser->banned_until->format('Y-m-d H:i:s');
                }
                return ['allowed' => false, 'message' => $banMessage];
            }

            if ($roomUser->isMuted()) {
                $muteMessage = 'You are muted in this room';
                if ($roomUser->muted_until) {
                    $muteMessage .= ' until ' . $roomUser->muted_until->format('Y-m-d H:i:s');
                }
                return ['allowed' => false, 'message' => $muteMessage];
            }
        }

        return ['allowed' => true];
    }

    /**
     * 检查速率限制
     */
    private function checkRateLimit(int $userId, int $roomId): array
    {
        $rateLimitKey = "send_message:{$userId}:{$roomId}";
        $rateLimitResult = $this->cacheService->checkRateLimit(
            $rateLimitKey, 
            self::RATE_LIMIT_MESSAGES_PER_MINUTE, 
            self::RATE_LIMIT_WINDOW_SECONDS
        );
        
        if (!$rateLimitResult['allowed']) {
            $resetTime = $rateLimitResult['reset_time']->diffInSeconds(now());
            return [
                'allowed' => false,
                'message' => "Too many messages. Please wait {$resetTime} seconds before sending another message.",
                'data' => [
                    'rate_limit' => [
                        'attempts' => $rateLimitResult['attempts'],
                        'remaining' => $rateLimitResult['remaining'],
                        'reset_time' => $rateLimitResult['reset_time']->toISOString()
                    ]
                ]
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Delete a message (for moderation).
     */
    public function deleteMessage(Request $request, int $roomId, int $messageId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // 验证房间和消息是否存在
            $room = ChatRoom::active()->findOrFail($roomId);
            $message = ChatMessage::where('room_id', $roomId)->findOrFail($messageId);

            // 检查用户是否有权限删除消息
            // 用户可以删除自己发送的消息或房间创建者可以删除任何消息
            $canDelete = $message->user_id === $userId || $room->created_by === $userId;

            if (!$canDelete) {
                return $this->errorResponse('You are not authorized to delete this message', [], 403);
            }

            DB::beginTransaction();

            // 存储消息信息用于广播
            $deletedMessageId = $message->id;
            $deletedRoomId = $message->room_id;

            // 删除消息
            $message->delete();

            DB::commit();

            // 广播删除事件
            broadcast(new MessageDeleted($deletedMessageId, $deletedRoomId, $userId));

            Log::info('Message deleted successfully', [
                'message_id' => $deletedMessageId,
                'room_id' => $deletedRoomId,
                'deleted_by' => $userId
            ]);

            return $this->successResponse([], 'Message deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete message', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to delete message', [], 500);
        }
    }

    /**
     * Get online users in a chat room.
     */
    public function getOnlineUsers(Request $request, int $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // 验证房间是否存在
            $room = ChatRoom::active()->findOrFail($roomId);
            
            // 验证用户是否在房间中
            if (!$this->validateUserInRoom($roomId, $userId)) {
                return $this->errorResponse('You must join the room to view online users', [], 403);
            }

            $onlineUsers = $this->chatService->getOnlineUsers($roomId);

            return $this->successResponse([
                'online_users' => $onlineUsers,
                'count' => $onlineUsers->count(),
            ], 'Online users retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to retrieve online users', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to retrieve online users', [], 500);
        }
    }

    /**
     * Update user status (heartbeat/presence tracking).
     */
    public function updateUserStatus(Request $request, int $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // 验证房间是否存在
            $room = ChatRoom::active()->findOrFail($roomId);
            
            $result = $this->chatService->processHeartbeat($roomId, $userId);

            if (!$result['success']) {
                return $this->errorResponse('Failed to update status', $result['errors'], 404);
            }

            return $this->successResponse([
                'last_seen_at' => $result['last_seen_at'],
            ], 'Status updated successfully');
        } catch (\Exception $e) {
            Log::error('Failed to update user status', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to update status', [], 500);
        }
    }

    /**
     * Cleanup disconnected users (called by scheduled task or manually).
     */
    public function cleanupDisconnectedUsers(Request $request): JsonResponse
    {
        try {
            $result = $this->chatService->cleanupInactiveUsers();

            if (!$result['success']) {
                return $this->errorResponse('Failed to cleanup disconnected users', $result['errors'], 500);
            }

            Log::info('Disconnected users cleanup completed', [
                'cleaned_users_count' => $result['cleaned_users'],
                'initiated_by' => $this->getCurrentUserId()
            ]);

            return $this->successResponse([
                'cleaned_users_count' => $result['cleaned_users'],
            ], $result['message']);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup disconnected users', [
                'error' => $e->getMessage(),
                'initiated_by' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to cleanup disconnected users', [], 500);
        }
    }

    /**
     * Get user's presence status in a specific room.
     */
    public function getUserPresenceStatus(Request $request, int $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // 验证房间是否存在
            $room = ChatRoom::active()->findOrFail($roomId);

            $roomUser = $this->getUserInRoom($roomId, $userId);

            if (!$roomUser) {
                return $this->successResponse([
                    'is_in_room' => false,
                    'is_online' => false,
                ], 'You are not in this room');
            }

            return $this->successResponse([
                'is_in_room' => true,
                'is_online' => $roomUser->is_online,
                'joined_at' => $roomUser->joined_at,
                'last_seen_at' => $roomUser->last_seen_at,
                'is_inactive' => $roomUser->isInactive(),
            ], 'User presence status retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Failed to get user presence status', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->errorResponse('Failed to get user presence status', [], 500);
        }
    }
}