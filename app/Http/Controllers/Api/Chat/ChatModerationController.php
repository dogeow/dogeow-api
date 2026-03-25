<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\BanChatUserRequest;
use App\Http\Requests\Chat\ChatModerationReasonRequest;
use App\Http\Requests\Chat\MuteChatUserRequest;
use App\Models\Chat\ChatMessage;
use App\Services\Chat\ChatModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ChatModerationController extends Controller
{
    use ChatControllerHelpers;

    public function __construct(
        private readonly ChatModerationService $moderationService
    ) {}

    /**
     * Delete a message (admin/moderator only).
     */
    public function deleteMessage(ChatModerationReasonRequest $request, int $roomId, int $messageId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $message = ChatMessage::where('room_id', $roomId)->findOrFail($messageId);
        $moderator = $this->getModerator();
        $validated = $request->validated();
        $reason = $validated['reason'] ?? null;

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to moderate this room');
        if ($guard) {
            return $guard;
        }

        try {
            $result = DB::transaction(function () use ($room, $moderator, $message, $reason) {
                return $this->moderationService->deleteMessage($room, $moderator, $message, $reason);
            });

            return $this->success($result, 'Message deleted successfully');

        } catch (\Exception $e) {
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
    public function muteUser(MuteChatUserRequest $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();
        $validated = $request->validated();
        $duration = $validated['duration'] ?? null;
        $reason = $validated['reason'] ?? null;

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

        $roomUser = $this->findRoomUser($roomId, $userId);

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        try {
            $result = DB::transaction(function () use ($room, $moderator, $roomUser, $duration, $reason) {
                return $this->moderationService->muteUser($room, $moderator, $roomUser, $duration, $reason);
            });

            return $this->success($result, 'User muted successfully');

        } catch (\Exception $e) {
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
    public function unmuteUser(ChatModerationReasonRequest $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();
        $validated = $request->validated();
        $reason = $validated['reason'] ?? null;

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to moderate this room');
        if ($guard) {
            return $guard;
        }

        $roomUser = $this->findRoomUser($roomId, $userId);

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        if (! $roomUser->isMuted()) {
            return $this->error('User is not muted', [], 422);
        }

        try {
            $result = DB::transaction(function () use ($room, $moderator, $roomUser, $reason) {
                return $this->moderationService->unmuteUser($room, $moderator, $roomUser, $reason);
            });

            return $this->success($result, 'User unmuted successfully');

        } catch (\Exception $e) {
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
    public function banUser(BanChatUserRequest $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();
        $validated = $request->validated();
        $duration = $validated['duration'] ?? null;
        $reason = $validated['reason'] ?? null;

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

        $roomUser = $this->findRoomUser($roomId, $userId);

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        try {
            $result = DB::transaction(function () use ($room, $moderator, $roomUser, $duration, $reason) {
                return $this->moderationService->banUser($room, $moderator, $roomUser, $duration, $reason);
            });

            return $this->success($result, 'User banned successfully');

        } catch (\Exception $e) {
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
    public function unbanUser(ChatModerationReasonRequest $request, int $roomId, int $userId): JsonResponse
    {
        $room = $this->findActiveRoom($roomId);
        $moderator = $this->getModerator();
        $validated = $request->validated();
        $reason = $validated['reason'] ?? null;

        // Check if user can moderate
        $guard = $this->ensureCanModerate($moderator, $room, 'You are not authorized to moderate this room');
        if ($guard) {
            return $guard;
        }

        $roomUser = $this->findRoomUser($roomId, $userId);

        if (! $roomUser) {
            return $this->error('User is not in this room', [], 404);
        }

        if (! $roomUser->isBanned()) {
            return $this->error('User is not banned', [], 422);
        }

        try {
            $result = DB::transaction(function () use ($room, $moderator, $roomUser, $reason) {
                return $this->moderationService->unbanUser($room, $moderator, $roomUser, $reason);
            });

            return $this->success($result, 'User unbanned successfully');

        } catch (\Exception $e) {
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
}
