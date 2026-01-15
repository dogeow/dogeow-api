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
     * 判断用户是否在房间
     */
    private function isUserInRoom(int $roomId, int $userId): bool
    {
        return ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 获取房间内用户信息
     */
    private function fetchRoomUser(int $roomId, int $userId): ?ChatRoomUser
    {
        return ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * 失效相关缓存
     */
    private function clearRoomCache(int $roomId): void
    {
        $this->cacheService->invalidateOnlineUsers($roomId);
        $this->cacheService->invalidateRoomStats($roomId);
        $this->cacheService->invalidateRoomList();
    }

    /**
     * 记录房间活动
     */
    private function logRoomActivity(int $roomId, string $action, int $userId): void
    {
        $this->cacheService->trackRoomActivity($roomId, $action, $userId);
    }

    protected function getPagination(Request $request): array
    {
        return $this->getPaginationParams($request, self::DEFAULT_PAGE_SIZE, self::MAX_PAGE_SIZE);
    }

    /**
     * 获取所有房间
     */
    public function getRooms(): JsonResponse
    {
        try {
            $rooms = $this->chatService->getActiveRooms();
            return $this->success(['rooms' => $rooms], 'Rooms retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve rooms', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to retrieve rooms', [], 500);
        }
    }

    /**
     * 创建房间
     */
    public function createRoom(CreateRoomRequest $request): JsonResponse
    {
        try {
            $result = $this->chatService->createRoom([
                'name' => $request->name,
                'description' => $request->description,
            ], $this->getCurrentUserId());

            if (empty($result['success'])) {
                return $this->error('Failed to create room', $result['errors'] ?? []);
            }

            Log::info('Room created', [
                'room_id' => $result['room']->id,
                'room_name' => $result['room']->name,
                'created_by' => $this->getCurrentUserId()
            ]);

            return $this->success(['room' => $result['room']], 'Room created successfully', 201);
        } catch (\Throwable $e) {
            Log::error('Failed to create room', [
                'error' => $e->getMessage(),
                'user_id' => $this->getCurrentUserId(),
                'room_name' => $request->name ?? 'unknown'
            ]);
            return $this->error('Failed to create room', [], 500);
        }
    }

    /**
     * 加入房间
     */
    public function joinRoom(Request $request, $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;
            $result = $this->chatService->joinRoom($roomId, $userId);

            if (empty($result['success'])) {
                return $this->error('Failed to join room', $result['errors'] ?? []);
            }

            $this->clearRoomCache($roomId);
            $this->logRoomActivity($roomId, 'user_joined', $userId);

            Log::info('User joined room', [
                'room_id' => $roomId,
                'user_id' => $userId
            ]);

            return $this->success([
                'room' => $result['room'],
                'room_user' => $result['room_user'],
            ], 'Successfully joined the room');
        } catch (\Throwable $e) {
            Log::error('Failed to join room', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to join room', [], 500);
        }
    }

    /**
     * 离开房间
     */
    public function leaveRoom(Request $request, $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;
            $result = $this->chatService->leaveRoom($roomId, $userId);

            if (empty($result['success'])) {
                return $this->error('Failed to leave room', $result['errors'] ?? []);
            }

            $this->clearRoomCache($roomId);
            $this->logRoomActivity($roomId, 'user_left', $userId);

            Log::info('User left room', [
                'room_id' => $roomId,
                'user_id' => $userId
            ]);

            return $this->success([], $result['message'] ?? 'Left room');
        } catch (\Throwable $e) {
            Log::error('Failed to leave room', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to leave room', [], 500);
        }
    }

    /**
     * 删除房间（仅创建者）
     */
    public function deleteRoom(Request $request, $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;
            $result = $this->chatService->deleteRoom($roomId, $userId);

            if (empty($result['success'])) {
                $statusCode = (isset($result['errors']) && in_array('You do not have permission to delete this room', $result['errors'])) ? 403 : 422;
                return $this->error('Failed to delete room', $result['errors'] ?? [], $statusCode);
            }

            Log::info('Room deleted', [
                'room_id' => $roomId,
                'deleted_by' => $userId
            ]);

            return $this->success([], $result['message'] ?? 'Room deleted');
        } catch (\Throwable $e) {
            Log::error('Failed to delete room', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to delete room', [], 500);
        }
    }

    /**
     * 获取房间消息（分页）
     */
    public function getMessages(Request $request, $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;

            $room = ChatRoom::active()->findOrFail($roomId);

            if (!$this->isUserInRoom($roomId, $userId)) {
                return $this->error('You must join the room to view messages', [], 403);
            }

            [$page, $perPage] = $this->getPagination($request);

            if ($page === 1) {
                $messages = $this->chatService->getRecentMessages($roomId, $perPage);
                return $this->success([
                    'messages' => $messages,
                    'pagination' => [
                        'current_page' => 1,
                        'has_more_pages' => $messages->count() === $perPage,
                    ],
                ], 'Messages retrieved successfully');
            } else {
                $paginated = $this->chatService->getMessageHistoryPaginated($roomId, $page, $perPage);
                return $this->success([
                    'messages' => array_reverse($paginated->items()),
                    'pagination' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                        'has_more_pages' => $paginated->hasMorePages(),
                    ],
                ], 'Messages retrieved successfully');
            }
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve messages', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to retrieve messages', [], 500);
        }
    }

    /**
     * 发送消息
     */
    public function sendMessage(SendMessageRequest $request, $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;

            $room = ChatRoom::active()->findOrFail($roomId);

            $roomUser = $this->fetchRoomUser($roomId, $userId);
            if (!$roomUser || !$roomUser->is_online) {
                return $this->error('You must be online in the room to send messages', [], 403);
            }

            $perm = $this->checkUserPermission($roomUser);
            if (!$perm['allowed']) {
                return $this->error($perm['message'], [], 403);
            }

            $rate = $this->checkRate($userId, $roomId);
            if (!$rate['allowed']) {
                return $this->error($rate['message'], $rate['data'] ?? [], 429);
            }

            $result = $this->chatService->processMessage(
                $roomId,
                $userId,
                $request->message,
                $request->message_type ?? ChatMessage::TYPE_TEXT
            );

            if (empty($result['success'])) {
                return $this->error('Failed to send message', $result['errors'] ?? []);
            }

            $roomUser->updateLastSeen();

            $this->clearRoomCache($roomId);
            $this->cacheService->invalidateMessageHistory($roomId);

            $this->logRoomActivity($roomId, 'message_sent', $userId);

            broadcast(new MessageSent($result['message']));

            Log::info('Message sent', [
                'message_id' => $result['message']->id,
                'room_id' => $roomId,
                'user_id' => $userId,
                'message_type' => $result['message']->message_type
            ]);

            return $this->success([
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
        } catch (\Throwable $e) {
            Log::error('Failed to send message', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to send message', [], 500);
        }
    }

    /**
     * 检查用户权限（禁言/封禁）
     */
    private function checkUserPermission(ChatRoomUser $roomUser): array
    {
        if (!$roomUser->canSendMessages()) {
            if ($roomUser->isBanned()) {
                $msg = 'You are banned from this room';
                if ($roomUser->banned_until) {
                    $msg .= ' until ' . $roomUser->banned_until->format('Y-m-d H:i:s');
                }
                return ['allowed' => false, 'message' => $msg];
            }
            if ($roomUser->isMuted()) {
                $msg = 'You are muted in this room';
                if ($roomUser->muted_until) {
                    $msg .= ' until ' . $roomUser->muted_until->format('Y-m-d H:i:s');
                }
                return ['allowed' => false, 'message' => $msg];
            }
        }
        return ['allowed' => true];
    }

    /**
     * 检查速率限制
     */
    private function checkRate(int $userId, int $roomId): array
    {
        $key = "send_message:{$userId}:{$roomId}";
        $res = $this->cacheService->checkRateLimit(
            $key,
            self::RATE_LIMIT_MESSAGES_PER_MINUTE,
            self::RATE_LIMIT_WINDOW_SECONDS
        );
        if (empty($res['allowed'])) {
            $reset = $res['reset_time']->diffInSeconds(now());
            return [
                'allowed' => false,
                'message' => "Too many messages. Please wait {$reset} seconds before sending another message.",
                'data' => [
                    'rate_limit' => [
                        'attempts' => $res['attempts'],
                        'remaining' => $res['remaining'],
                        'reset_time' => $res['reset_time']->toISOString()
                    ]
                ]
            ];
        }
        return ['allowed' => true];
    }

    /**
     * 删除消息（管理）
     */
    public function deleteMessage(Request $request, $roomId, $messageId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;
            $messageId = (int)$messageId;

            $room = ChatRoom::active()->findOrFail($roomId);
            $message = ChatMessage::where('room_id', $roomId)->findOrFail($messageId);

            $canDelete = $message->user_id === $userId || $room->created_by === $userId;
            if (!$canDelete) {
                return $this->error('You are not authorized to delete this message', [], 403);
            }

            DB::beginTransaction();
            $deletedMessageId = $message->id;
            $deletedRoomId = $message->room_id;
            $message->delete();
            DB::commit();

            broadcast(new MessageDeleted($deletedMessageId, $deletedRoomId, $userId));

            Log::info('Message deleted', [
                'message_id' => $deletedMessageId,
                'room_id' => $deletedRoomId,
                'deleted_by' => $userId
            ]);

            return $this->success([], 'Message deleted successfully');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete message', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to delete message', [], 500);
        }
    }

    /**
     * 获取房间在线用户
     */
    public function getOnlineUsers(Request $request, $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;

            $room = ChatRoom::active()->findOrFail($roomId);

            if (!$this->isUserInRoom($roomId, $userId)) {
                return $this->error('You must join the room to view online users', [], 403);
            }

            $onlineUsers = $this->chatService->getOnlineUsers($roomId);

            return $this->success([
                'online_users' => $onlineUsers,
                'count' => $onlineUsers->count(),
            ], 'Online users retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve online users', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to retrieve online users', [], 500);
        }
    }

    /**
     * 更新用户状态（心跳）
     */
    public function updateUserStatus(Request $request, $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;

            $room = ChatRoom::active()->findOrFail($roomId);

            $result = $this->chatService->processHeartbeat($roomId, $userId);

            if (empty($result['success'])) {
                return $this->error('Failed to update status', $result['errors'] ?? [], 404);
            }

            return $this->success([
                'last_seen_at' => $result['last_seen_at'],
            ], 'Status updated successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to update user status', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to update status', [], 500);
        }
    }

    /**
     * 清理离线用户
     */
    public function cleanupDisconnectedUsers(Request $request): JsonResponse
    {
        try {
            $result = $this->chatService->cleanupInactiveUsers();

            if (empty($result['success'])) {
                return $this->error('Failed to cleanup disconnected users', $result['errors'] ?? [], 500);
            }

            Log::info('Disconnected users cleanup', [
                'cleaned_users_count' => $result['cleaned_count'],
                'initiated_by' => $this->getCurrentUserId()
            ]);

            return $this->success([
                'cleaned_users_count' => $result['cleaned_count'],
            ], $result['message'] ?? 'Cleanup done');
        } catch (\Throwable $e) {
            Log::error('Failed to cleanup disconnected users', [
                'error' => $e->getMessage(),
                'initiated_by' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to cleanup disconnected users', [], 500);
        }
    }

    /**
     * 获取用户在房间的状态
     */
    public function getUserPresenceStatus(Request $request, $roomId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $roomId = (int)$roomId;

            $room = ChatRoom::active()->findOrFail($roomId);

            $roomUser = $this->fetchRoomUser($roomId, $userId);

            if (!$roomUser) {
                return $this->success([
                    'is_in_room' => false,
                    'is_online' => false,
                ], 'You are not in this room');
            }

            return $this->success([
                'is_in_room' => true,
                'is_online' => $roomUser->is_online,
                'joined_at' => $roomUser->joined_at,
                'last_seen_at' => $roomUser->last_seen_at,
                'is_inactive' => $roomUser->isInactive(),
            ], 'User presence status retrieved successfully');
        } catch (\Throwable $e) {
            Log::error('Failed to get user presence status', [
                'error' => $e->getMessage(),
                'room_id' => $roomId,
                'user_id' => $this->getCurrentUserId()
            ]);
            return $this->error('Failed to get user presence status', [], 500);
        }
    }
}