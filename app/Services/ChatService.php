<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use App\Models\User;
use App\Events\UserJoinedRoom;
use App\Events\UserLeftRoom;
use App\Utils\CharLengthHelper;
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
     * 消息验证规则
     */
    const MAX_MESSAGE_LENGTH = 1000;
    const MIN_MESSAGE_LENGTH = 1;
    
    /**
     * 分页设置
     */
    const DEFAULT_PAGE_SIZE = 50;
    const MAX_PAGE_SIZE = 100;
    
    /**
     * 房间验证规则（按字符数计算：中文/emoji算2，数字/字母算1）
     */
    const MAX_ROOM_NAME_LENGTH = 20; // 最多20个字符
    const MIN_ROOM_NAME_LENGTH = 2; // 最少2个字符
    const MAX_ROOM_DESCRIPTION_LENGTH = 500;

    /**
     * 在处理前验证和清理消息
     *
     * @param string $message
     * @return array
     */
    public function validateMessage(string $message): array
    {
        $errors = [];
        
        // 去除空白字符
        $message = trim($message);
        
        // 检查长度限制
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
     * 清理消息内容以防止XSS和其他安全问题
     *
     * @param string $message
     * @return string
     */
    public function sanitizeMessage(string $message): string
    {
        // 移除任何HTML标签及其内容
        $message = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $message);
        $message = strip_tags($message);
        
        // 标准化空白字符
        $message = preg_replace('/\s+/', ' ', $message);
        
        return trim($message);
    }

    /**
     * 检测和处理消息中的用户提及
     *
     * @param string $message
     * @return array
     */
    public function processMentions(string $message): array
    {
        $mentions = [];
        
        // 匹配@用户名提及的模式
        $pattern = '/@([a-zA-Z0-9_.-]+)/';
        
        if (preg_match_all($pattern, $message, $matches)) {
            $usernames = $matches[1];
            
            // 按名称查找用户（不区分大小写）
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
     * 格式化消息，支持表情符号和提及高亮
     *
     * @param string $message
     * @param array $mentions
     * @return string
     */
    public function formatMessage(string $message, array $mentions = []): string
    {
        // 处理提及 - 用特殊标记包装以便前端高亮显示
        foreach ($mentions as $mention) {
            $pattern = '/@' . preg_quote($mention['username'], '/') . '/i';
            $replacement = '<mention data-user-id="' . $mention['user_id'] . '">@' . $mention['username'] . '</mention>';
            $message = preg_replace($pattern, $replacement, $message);
        }
        
        // 将常见文本表情符号转换为表情符号
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
     * 使用基于游标的分页获取房间的分页消息历史
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
     * 获取房间的最近消息（用于初始加载）并缓存
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
     * 获取分页消息历史以保持向后兼容性
     *
     * @param int $roomId
     * @param int $page
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getMessageHistoryPaginated(int $roomId, int $page = 1, int $perPage = self::DEFAULT_PAGE_SIZE): LengthAwarePaginator
    {
        // 确保页面大小不超过最大值
        $perPage = min($perPage, self::MAX_PAGE_SIZE);
        
        return ChatMessage::with(['user:id,name,email'])
            ->forRoom($roomId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * 处理并创建新消息
     *
     * @param int $roomId
     * @param int $userId
     * @param string $message
     * @param string $messageType
     * @return array
     */
    public function processMessage(int $roomId, int $userId, string $message, string $messageType = ChatMessage::TYPE_TEXT): array
    {
        // 验证消息
        $validation = $this->validateMessage($message);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors']
            ];
        }
        
        $sanitizedMessage = $validation['sanitized_message'];
        
        // 对文本消息应用内容过滤
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
            
            // 如果内容被修改，使用过滤后的消息
            $sanitizedMessage = $filterResult['filtered_message'];
        }
        
        // 处理提及
        $mentions = $this->processMentions($sanitizedMessage);
        
        // 格式化消息
        $formattedMessage = $this->formatMessage($sanitizedMessage, $mentions);
        
        try {
            // 创建消息
            $chatMessage = ChatMessage::create([
                'room_id' => $roomId,
                'user_id' => $userId,
                'message' => $formattedMessage,
                'message_type' => $messageType
            ]);
            
            // 加载用户关系
            $chatMessage->load('user:id,name,email');
            
            $result = [
                'success' => true,
                'message' => $chatMessage,
                'mentions' => $mentions,
                'original_message' => $sanitizedMessage
            ];
            
            // 如果应用了过滤，包含过滤信息
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
     * 创建系统消息
     * 注意：由于数据库需要user_id，我们将使用系统用户（用户ID 1）来创建系统消息
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
                'user_id' => $systemUserId, // 使用系统用户ID
                'message' => $this->sanitizeMessage($message),
                'message_type' => ChatMessage::TYPE_SYSTEM
            ]);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 使用基于游标的分页在房间中搜索消息
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
     * 获取房间的消息统计信息
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
    // 房间管理方法
    // ========================================

    /**
     * 验证房间创建数据
     *
     * @param array $data
     * @return array
     */
    public function validateRoomData(array $data): array
    {
        $errors = [];
        
        // 验证房间名称
        if (empty($data['name'])) {
            $errors[] = '房间名称是必需的';
        } else {
            $name = trim($data['name']);
            
            // 使用字符长度计算（中文/emoji算2，数字/字母算1）
            if (CharLengthHelper::belowMinLength($name, self::MIN_ROOM_NAME_LENGTH)) {
                $errors[] = '房间名称至少需要' . self::MIN_ROOM_NAME_LENGTH . '个字符';
            }
            if (CharLengthHelper::exceedsMaxLength($name, self::MAX_ROOM_NAME_LENGTH)) {
                $errors[] = '房间名称不能超过' . self::MAX_ROOM_NAME_LENGTH . '个字符（中文/emoji算2个字符，数字/字母算1个字符）';
            }
            
            // 检查重复的房间名称
            if (ChatRoom::where('name', $name)->where('is_active', true)->exists()) {
                $errors[] = '该房间名称已存在';
            }
        }
        
        // 如果提供了描述，验证描述
        if (!empty($data['description'])) {
            $description = trim($data['description']);
            if (mb_strlen($description, 'UTF-8') > self::MAX_ROOM_DESCRIPTION_LENGTH) {
                $errors[] = '房间描述不能超过' . self::MAX_ROOM_DESCRIPTION_LENGTH . '个字符';
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
     * 创建新的聊天房间
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
            
            // 自动将创建者加入房间
            ChatRoomUser::create([
                'room_id' => $room->id,
                'user_id' => $createdBy,
                'joined_at' => now(),
                'is_online' => true
            ]);
            
            // 为房间创建创建系统消息
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
     * 检查用户是否有权限执行房间操作
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
                // 只有房间创建者或管理员可以删除/编辑房间
                return $room->created_by === $userId || $user->hasRole('admin');
                
            case 'join':
                // 任何已认证用户都可以加入活跃房间
                return true;
                
            case 'moderate':
                // 房间创建者或管理员可以管理
                return $room->created_by === $userId || $user->hasRole('admin');
                
            default:
                return false;
        }
    }

    /**
     * 删除聊天房间（带安全检查）
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
        
        // 检查房间是否有活跃用户（不包括创建者）
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
            
            // 在删除前创建系统消息
            $this->createSystemMessage($roomId, "Room '{$room->name}' is being deleted", $userId);
            
            // 软删除：标记为不活跃而不是硬删除，以保留消息历史
            $room->update(['is_active' => false]);
            
            // 移除所有用户关联
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
     * 更新房间信息
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
        
        // 验证新数据
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
            
            // 如果名称改变，创建系统消息
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
     * 获取房间统计和分析数据
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
     * 获取活跃房间列表及基本统计信息（缓存）
     *
     * @return Collection
     */
    public function getActiveRooms(): Collection
    {
        return $this->cacheService->getRoomList();
    }

    // ========================================
    // 用户在线状态方法
    // ========================================

    /**
     * 在线状态超时设置
     */
    const PRESENCE_TIMEOUT_MINUTES = 5;
    const HEARTBEAT_INTERVAL_SECONDS = 30;

    /**
     * 更新用户在房间中的在线状态
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
                // 用户不在房间中，如果上线则创建条目
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
                // 更新现有条目
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
     * 处理用户加入房间
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
            // 检查用户是否已经是成员
            $existingRoomUser = ChatRoomUser::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();
            
            if ($existingRoomUser) {
                // 用户已经是成员，只更新在线状态
                $result = $this->updateUserStatus($roomId, $userId, true);
                return [
                    'success' => true,
                    'room_user' => $result['room_user'],
                    'room' => $room,
                    'message' => 'User is already a member of this room'
                ];
            }
            
            DB::beginTransaction();
            
            // 更新或创建用户在线状态
            $result = $this->updateUserStatus($roomId, $userId, true);
            
            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }
            
            // 为用户加入创建系统消息
            $user = User::find($userId);
            $this->createSystemMessage($roomId, "{$user->name} joined the room", $userId);
            
            // 获取当前在线人数
            $onlineCount = ChatRoomUser::where('room_id', $roomId)
                ->where('is_online', true)
                ->count();
            
            // 广播用户加入事件
            broadcast(new \App\Events\Chat\UserJoined($user, $roomId));
            
            // 广播在线人数变化事件
            broadcast(new UserJoinedRoom($roomId, $userId, $user->name, $onlineCount));
            
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
     * 处理用户离开房间
     *
     * @param int $roomId
     * @param int $userId
     * @return array
     */
    public function leaveRoom(int $roomId, int $userId): array
    {
        try {
            // 检查用户是否是成员
            $roomUser = ChatRoomUser::where('room_id', $roomId)
                ->where('user_id', $userId)
                ->first();
            
            if (!$roomUser) {
                return [
                    'success' => false,
                    'message' => 'User is not a member of this room'
                ];
            }
            
            DB::beginTransaction();
            
            // 将用户状态更新为离线
            $result = $this->updateUserStatus($roomId, $userId, false);
            
            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }
            
            // 为用户离开创建系统消息
            $user = User::find($userId);
            $this->createSystemMessage($roomId, "{$user->name} left the room", $userId);
            
            // 获取当前在线人数
            $onlineCount = ChatRoomUser::where('room_id', $roomId)
                ->where('is_online', true)
                ->count();
            
            // 广播用户离开事件
            broadcast(new \App\Events\Chat\UserLeft($user, $roomId));
            
            // 广播在线人数变化事件
            broadcast(new UserLeftRoom($roomId, $userId, $user->name, $onlineCount));
            
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
     * 获取房间的在线用户（缓存）
     *
     * @param int $roomId
     * @return Collection
     */
    public function getOnlineUsers(int $roomId): Collection
    {
        return $this->cacheService->getOnlineUsers($roomId);
    }

    /**
     * 处理心跳以保持用户在线
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
     * 清理非活跃用户（标记为离线）
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
                
                // 为用户因不活跃而离线创建系统消息
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
                'cleaned_count' => $cleanedCount,
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
     * 获取房间的用户活动跟踪
     *
     * @param int $roomId
     * @param int $hours
     * @return array
     */
    public function getUserActivity(int $roomId, int $hours = 24): array
    {
        $since = now()->subHours($hours);
        
        // 获取在时间段内活跃的用户
        $activeUsers = ChatRoomUser::where('room_id', $roomId)
            ->where('last_seen_at', '>=', $since)
            ->with('user:id,name,email')
            ->get();
        
        // 获取按用户分组的消息活动
        $messageActivity = ChatMessage::forRoom($roomId)
            ->where('created_at', '>=', $since)
            ->select('user_id', DB::raw('COUNT(*) as message_count'))
            ->groupBy('user_id')
            ->with('user:id,name,email')
            ->get();
        
        // 获取加入/离开活动
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
     * 获取所有房间的在线状态统计
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