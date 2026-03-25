<?php

namespace App\Services\Chat;

use App\Events\Chat\MessageDeleted;
use App\Events\Chat\UserBanned;
use App\Events\Chat\UserMuted;
use App\Events\Chat\UserUnbanned;
use App\Events\Chat\UserUnmuted;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatModerationAction;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ChatModerationService
{
    /**
     * Delete a message
     *
     * @return array{action: string, moderator: string, reason: ?string}
     */
    public function deleteMessage(ChatRoom $room, User $moderator, ChatMessage $message, ?string $reason): array
    {
        // Log the moderation action
        $moderationAction = ChatModerationAction::create([
            'room_id' => $room->id,
            'moderator_id' => $moderator->id,
            'target_user_id' => $message->user_id,
            'message_id' => $message->id,
            'action_type' => ChatModerationAction::ACTION_DELETE_MESSAGE,
            'reason' => $reason,
            'metadata' => [
                'original_message' => $message->message,
                'message_type' => $message->message_type,
            ],
        ]);

        // Remove message reference before deleting to avoid cascade
        $moderationAction->update(['message_id' => null]);

        // Delete the message
        $message->delete();

        // Broadcast the deletion
        broadcast(new MessageDeleted($message->id, $room->id, $moderator->id, $reason));

        return [
            'action' => 'delete_message',
            'moderator' => $moderator->name,
            'reason' => $reason,
        ];
    }

    /**
     * Mute a user
     *
     * @return array{action: string, target_user_id: int, moderator: string, duration_minutes: ?int, reason: ?string, muted_until: ?string}
     */
    public function muteUser(ChatRoom $room, User $moderator, ChatRoomUser $roomUser, ?int $duration, ?string $reason): array
    {
        // Mute the user
        $roomUser->mute($moderator->id, $duration, $reason);

        // Log the moderation action
        ChatModerationAction::create([
            'room_id' => $room->id,
            'moderator_id' => $moderator->id,
            'target_user_id' => $roomUser->user_id,
            'action_type' => ChatModerationAction::ACTION_MUTE_USER,
            'reason' => $reason,
            'metadata' => [
                'duration_minutes' => $duration,
                'muted_until' => $duration ? now()->addMinutes($duration)->toISOString() : null,
            ],
        ]);

        // Broadcast the mute action
        broadcast(new UserMuted($room->id, $roomUser->user_id, $moderator->id, $duration, $reason));

        return [
            'action' => 'mute_user',
            'target_user_id' => $roomUser->user_id,
            'moderator' => $moderator->name,
            'duration_minutes' => $duration,
            'reason' => $reason,
            'muted_until' => $duration ? now()->addMinutes($duration)->toISOString() : null,
        ];
    }

    /**
     * Unmute a user
     *
     * @return array{action: string, target_user_id: int, moderator: string, reason: ?string}
     */
    public function unmuteUser(ChatRoom $room, User $moderator, ChatRoomUser $roomUser, ?string $reason): array
    {
        // Unmute the user
        $roomUser->unmute();

        // Log the moderation action
        ChatModerationAction::create([
            'room_id' => $room->id,
            'moderator_id' => $moderator->id,
            'target_user_id' => $roomUser->user_id,
            'action_type' => ChatModerationAction::ACTION_UNMUTE_USER,
            'reason' => $reason,
        ]);

        // Broadcast the unmute action
        broadcast(new UserUnmuted($room->id, $roomUser->user_id, $moderator->id, $reason));

        return [
            'action' => 'unmute_user',
            'target_user_id' => $roomUser->user_id,
            'moderator' => $moderator->name,
            'reason' => $reason,
        ];
    }

    /**
     * Ban a user
     *
     * @return array{action: string, target_user_id: int, moderator: string, duration_minutes: ?int, reason: ?string, banned_until: ?string}
     */
    public function banUser(ChatRoom $room, User $moderator, ChatRoomUser $roomUser, ?int $duration, ?string $reason): array
    {
        // Ban the user
        $roomUser->ban($moderator->id, $duration, $reason);

        // Log the moderation action
        ChatModerationAction::create([
            'room_id' => $room->id,
            'moderator_id' => $moderator->id,
            'target_user_id' => $roomUser->user_id,
            'action_type' => ChatModerationAction::ACTION_BAN_USER,
            'reason' => $reason,
            'metadata' => [
                'duration_minutes' => $duration,
                'banned_until' => $duration ? now()->addMinutes($duration)->toISOString() : null,
            ],
        ]);

        // Broadcast the ban action
        broadcast(new UserBanned($room->id, $roomUser->user_id, $moderator->id, $duration, $reason));

        return [
            'action' => 'ban_user',
            'target_user_id' => $roomUser->user_id,
            'moderator' => $moderator->name,
            'duration_minutes' => $duration,
            'reason' => $reason,
            'banned_until' => $duration ? now()->addMinutes($duration)->toISOString() : null,
        ];
    }

    /**
     * Unban a user
     *
     * @return array{action: string, target_user_id: int, moderator: string, reason: ?string}
     */
    public function unbanUser(ChatRoom $room, User $moderator, ChatRoomUser $roomUser, ?string $reason): array
    {
        // Unban the user
        $roomUser->unban();

        // Log the moderation action
        ChatModerationAction::create([
            'room_id' => $room->id,
            'moderator_id' => $moderator->id,
            'target_user_id' => $roomUser->user_id,
            'action_type' => ChatModerationAction::ACTION_UNBAN_USER,
            'reason' => $reason,
        ]);

        // Broadcast the unban action
        broadcast(new UserUnbanned($room->id, $roomUser->user_id, $moderator->id, $reason));

        return [
            'action' => 'unban_user',
            'target_user_id' => $roomUser->user_id,
            'moderator' => $moderator->name,
            'reason' => $reason,
        ];
    }
}
