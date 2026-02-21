<?php

namespace App\Http\Controllers\Api\Chat;

use App\Events\Chat\MessageDeleted;
use App\Events\Chat\UserBanned;
use App\Events\Chat\UserMuted;
use App\Events\Chat\UserUnbanned;
use App\Events\Chat\UserUnmuted;
use App\Http\Controllers\Controller;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatModerationAction;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatModerationController extends Controller
{
    /**
     * 获取活跃房间
     */
    private function findActiveRoom(int $roomId): ChatRoom
    {
        return ChatRoom::active()->findOrFail($roomId);
    }

    /**
     * 获取当前操作员
     */
    private function getModerator(): User
    {
        return Auth::user();
    }

    /**
     * 检查是否有房间管理权限
     */
    private function ensureCanModerate(User $moderator, ChatRoom $room, string $message): ?JsonResponse
    {
        if (! $moderator->canModerate($room)) {
            return $this->error($message, [], 403);
        }

        return null;
    }

    /**
     * 防止对自己执行操作
     */
    private function ensureNotSelfModeration(int $moderatorId, int $targetUserId, string $message): ?JsonResponse
    {
        if ($targetUserId === $moderatorId) {
            return $this->error($message, [], 422);
        }

        return null;
    }

    /**
     * 获取房间成员记录
     */
    private function findRoomUser(int $roomId, int $userId): ?ChatRoomUser
    {
        return ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * 统一记录错误并返回错误响应
     */
    private function logAndError(string $logMessage, \Throwable $e, array $context, string $userMessage, int $statusCode = 500): JsonResponse
    {
        Log::error($logMessage, array_merge($context, [
            'error' => $e->getMessage(),
        ]));

        return $this->error($userMessage, [], $statusCode);
    }

    /**
     * 解析分页与筛选参数
     */
    private function parseModerationFilters(Request $request): array
    {
        return [
            'per_page' => $request->get('per_page', 20),
            'action_type' => $request->get('action_type'),
            'target_user_id' => $request->get('target_user_id'),
        ];
    }

    /**
     * Delete a message (admin/moderator only).
     */
    public function deleteMessage(Request $request, int $roomId, int $messageId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $message = ChatMessage::where('room_id', $roomId)->findOrFail($messageId);
        $moderator = $this->getModerator();

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to moderate this room');
        if ($guard) {
            return $guard;
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Log the moderation action
            $moderationAction = ChatModerationAction::create([
                'room_id' => $roomId,
                'moderator_id' => $moderator->id,
                'target_user_id' => $message->user_id,
                'message_id' => $messageId,
                'action_type' => ChatModerationAction::ACTION_DELETE_MESSAGE,
                'reason' => $request->reason,
                'metadata' => [
                    'original_message' => $message->message,
                    'message_type' => $message->message_type,
                ],
            ]);

            // Remove message reference before deleting to avoid cascade
            $moderationAction->update(['message_id' => null]);

            // Delete the message
            $message->delete();

            DB::commit();

            // Broadcast the deletion
            broadcast(new MessageDeleted($messageId, $roomId, $moderator->id, $request->reason));

            return $this->success([
                'action' => 'delete_message',
                'moderator' => $moderator->name,
                'reason' => $request->reason,
            ], 'Message deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->logAndError(
                'Failed to delete message',
                $e,
                [
                    'room_id' => $roomId,
                    'message_id' => $messageId,
                    'moderator_id' => $moderator->id,
                ],
                'Failed to delete message'
            );
        }
    }

    /**
     * Mute a user in a room.
     */
    public function muteUser(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to moderate this room');
        if ($guard) {
            return $guard;
        }

        // Prevent self-moderation
        $guard = $this->ensureNotSelfModeration($moderator->id, $userId, 'You cannot mute yourself');
        if ($guard) {
            return $guard;
        }

        $request->validate([
            'duration' => 'nullable|integer|min:1|max:10080', // Max 1 week in minutes
            'reason' => 'nullable|string|max:500',
        ]);

        $roomUser = $this->findRoomUser($roomId, $userId);

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        try {
            DB::beginTransaction();

            // Mute the user
            $roomUser->mute($moderator->id, $request->duration, $request->reason);

            // Log the moderation action
            ChatModerationAction::create([
                'room_id' => $roomId,
                'moderator_id' => $moderator->id,
                'target_user_id' => $userId,
                'action_type' => ChatModerationAction::ACTION_MUTE_USER,
                'reason' => $request->reason,
                'metadata' => [
                    'duration_minutes' => $request->duration,
                    'muted_until' => $request->duration ? now()->addMinutes($request->duration)->toISOString() : null,
                ],
            ]);

            DB::commit();

            // Broadcast the mute action
            broadcast(new UserMuted($roomId, $userId, $moderator->id, $request->duration, $request->reason));

            return $this->success([
                'action' => 'mute_user',
                'target_user_id' => $userId,
                'moderator' => $moderator->name,
                'duration_minutes' => $request->duration,
                'reason' => $request->reason,
                'muted_until' => $request->duration ? now()->addMinutes($request->duration)->toISOString() : null,
            ], 'User muted successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->logAndError(
                'Failed to mute user',
                $e,
                [
                    'room_id' => $roomId,
                    'target_user_id' => $userId,
                    'moderator_id' => $moderator->id,
                ],
                'Failed to mute user'
            );
        }
    }

    /**
     * Unmute a user in a room.
     */
    public function unmuteUser(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to moderate this room');
        if ($guard) {
            return $guard;
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $roomUser = $this->findRoomUser($roomId, $userId);

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        if (! $roomUser->isMuted()) {
            return $this->error('User is not muted', [], 422);
        }

        try {
            DB::beginTransaction();

            // Unmute the user
            $roomUser->unmute();

            // Log the moderation action
            ChatModerationAction::create([
                'room_id' => $roomId,
                'moderator_id' => $moderator->id,
                'target_user_id' => $userId,
                'action_type' => ChatModerationAction::ACTION_UNMUTE_USER,
                'reason' => $request->reason,
            ]);

            DB::commit();

            // Broadcast the unmute action
            broadcast(new UserUnmuted($roomId, $userId, $moderator->id, $request->reason));

            return $this->success([
                'action' => 'unmute_user',
                'target_user_id' => $userId,
                'moderator' => $moderator->name,
                'reason' => $request->reason,
            ], 'User unmuted successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->logAndError(
                'Failed to unmute user',
                $e,
                [
                    'room_id' => $roomId,
                    'target_user_id' => $userId,
                    'moderator_id' => $moderator->id,
                ],
                'Failed to unmute user'
            );
        }
    }

    /**
     * Ban a user from a room.
     */
    public function banUser(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to moderate this room');
        if ($guard) {
            return $guard;
        }

        // Prevent self-moderation
        $guard = $this->ensureNotSelfModeration($moderator->id, $userId, 'You cannot ban yourself');
        if ($guard) {
            return $guard;
        }

        $request->validate([
            'duration' => 'nullable|integer|min:1|max:525600', // Max 1 year in minutes
            'reason' => 'nullable|string|max:500',
        ]);

        $roomUser = $this->findRoomUser($roomId, $userId);

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        try {
            DB::beginTransaction();

            // Ban the user
            $roomUser->ban($moderator->id, $request->duration, $request->reason);

            // Log the moderation action
            ChatModerationAction::create([
                'room_id' => $roomId,
                'moderator_id' => $moderator->id,
                'target_user_id' => $userId,
                'action_type' => ChatModerationAction::ACTION_BAN_USER,
                'reason' => $request->reason,
                'metadata' => [
                    'duration_minutes' => $request->duration,
                    'banned_until' => $request->duration ? now()->addMinutes($request->duration)->toISOString() : null,
                ],
            ]);

            DB::commit();

            // Broadcast the ban action
            broadcast(new UserBanned($roomId, $userId, $moderator->id, $request->duration, $request->reason));

            return $this->success([
                'action' => 'ban_user',
                'target_user_id' => $userId,
                'moderator' => $moderator->name,
                'duration_minutes' => $request->duration,
                'reason' => $request->reason,
                'banned_until' => $request->duration ? now()->addMinutes($request->duration)->toISOString() : null,
            ], 'User banned successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->logAndError(
                'Failed to ban user',
                $e,
                [
                    'room_id' => $roomId,
                    'target_user_id' => $userId,
                    'moderator_id' => $moderator->id,
                ],
                'Failed to ban user'
            );
        }
    }

    /**
     * Unban a user from a room.
     */
    public function unbanUser(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to moderate this room');
        if ($guard) {
            return $guard;
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $roomUser = $this->findRoomUser($roomId, $userId);

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        if (! $roomUser->isBanned()) {
            return $this->error('User is not banned', [], 422);
        }

        try {
            DB::beginTransaction();

            // Unban the user
            $roomUser->unban();

            // Log the moderation action
            ChatModerationAction::create([
                'room_id' => $roomId,
                'moderator_id' => $moderator->id,
                'target_user_id' => $userId,
                'action_type' => ChatModerationAction::ACTION_UNBAN_USER,
                'reason' => $request->reason,
            ]);

            DB::commit();

            // Broadcast the unban action
            broadcast(new UserUnbanned($roomId, $userId, $moderator->id, $request->reason));

            return $this->success([
                'action' => 'unban_user',
                'target_user_id' => $userId,
                'moderator' => $moderator->name,
                'reason' => $request->reason,
            ], 'User unbanned successfully');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->logAndError(
                'Failed to unban user',
                $e,
                [
                    'room_id' => $roomId,
                    'target_user_id' => $userId,
                    'moderator_id' => $moderator->id,
                ],
                'Failed to unban user'
            );
        }
    }

    /**
     * Get moderation actions for a room.
     */
    public function getModerationActions(Request $request, int $roomId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to view moderation actions for this room');
        if ($guard) {
            return $guard;
        }

        $filters = $this->parseModerationFilters($request);

        $query = ChatModerationAction::forRoom($roomId)
            ->with(['moderator:id,name,email', 'targetUser:id,name,email', 'message:id,message'])
            ->orderBy('created_at', 'desc');

        if ($filters['action_type']) {
            $query->ofType($filters['action_type']);
        }

        if ($filters['target_user_id']) {
            $query->onUser($filters['target_user_id']);
        }

        $paged = $query->jsonPaginate();

        // Spatie 返回 JSON:API 格式（data/meta/links），直接返回给客户端
        return response()->json($paged);
    }

    /**
     * Get user's moderation status in a room.
     */
    public function getUserModerationStatus(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to view moderation status for this room');
        if ($guard) {
            return $guard;
        }

        $roomUser = $this->findRoomUser($roomId, $userId);
        if ($roomUser) {
            $roomUser->load(['user:id,name,email', 'mutedByUser:id,name', 'bannedByUser:id,name']);
        }

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        return $this->success([
            'user' => $roomUser->user,
            'moderation_status' => [
                'is_muted' => $roomUser->isMuted(),
                'muted_until' => $roomUser->muted_until?->toISOString(),
                'muted_by' => $roomUser->mutedByUser,
                'is_banned' => $roomUser->isBanned(),
                'banned_until' => $roomUser->banned_until?->toISOString(),
                'banned_by' => $roomUser->bannedByUser,
                'can_send_messages' => $roomUser->canSendMessages(),
            ],
        ], 'User moderation status retrieved successfully');
    }
}
