<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatRoom;
use App\Models\ChatMessage;
use App\Models\ChatRoomUser;
use App\Models\ChatModerationAction;
use App\Events\Chat\MessageDeleted;
use App\Events\Chat\UserMuted;
use App\Events\Chat\UserUnmuted;
use App\Events\Chat\UserBanned;
use App\Events\Chat\UserUnbanned;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChatModerationController extends Controller
{
    /**
     * Delete a message (admin/moderator only).
     */
    public function deleteMessage(Request $request, int $roomId, int $messageId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $message = ChatMessage::where('room_id', $roomId)->findOrFail($messageId);
        $moderator = Auth::user();

        // Check if user can moderate
        if (!$moderator->canModerate($room)) {
            return response()->json([
                'message' => 'You are not authorized to moderate this room'
            ], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Log the moderation action
            ChatModerationAction::create([
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

            // Delete the message
            $message->delete();

            DB::commit();

            // Broadcast the deletion
            broadcast(new MessageDeleted($messageId, $roomId, $moderator->id, $request->reason));

            return response()->json([
                'message' => 'Message deleted successfully',
                'action' => 'delete_message',
                'moderator' => $moderator->name,
                'reason' => $request->reason,
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
     * Mute a user in a room.
     */
    public function muteUser(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $moderator = Auth::user();

        // Check if user can moderate
        if (!$moderator->canModerate($room)) {
            return response()->json([
                'message' => 'You are not authorized to moderate this room'
            ], 403);
        }

        // Prevent self-moderation
        if ($userId === $moderator->id) {
            return response()->json([
                'message' => 'You cannot mute yourself'
            ], 422);
        }

        $request->validate([
            'duration' => 'nullable|integer|min:1|max:10080', // Max 1 week in minutes
            'reason' => 'nullable|string|max:500',
        ]);

        $roomUser = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$roomUser) {
            return response()->json([
                'message' => 'User is not in this room'
            ], 404);
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

            return response()->json([
                'message' => 'User muted successfully',
                'action' => 'mute_user',
                'target_user_id' => $userId,
                'moderator' => $moderator->name,
                'duration_minutes' => $request->duration,
                'reason' => $request->reason,
                'muted_until' => $request->duration ? now()->addMinutes($request->duration)->toISOString() : null,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to mute user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unmute a user in a room.
     */
    public function unmuteUser(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $moderator = Auth::user();

        // Check if user can moderate
        if (!$moderator->canModerate($room)) {
            return response()->json([
                'message' => 'You are not authorized to moderate this room'
            ], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $roomUser = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$roomUser) {
            return response()->json([
                'message' => 'User is not in this room'
            ], 404);
        }

        if (!$roomUser->isMuted()) {
            return response()->json([
                'message' => 'User is not muted'
            ], 422);
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

            return response()->json([
                'message' => 'User unmuted successfully',
                'action' => 'unmute_user',
                'target_user_id' => $userId,
                'moderator' => $moderator->name,
                'reason' => $request->reason,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to unmute user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ban a user from a room.
     */
    public function banUser(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $moderator = Auth::user();

        // Check if user can moderate
        if (!$moderator->canModerate($room)) {
            return response()->json([
                'message' => 'You are not authorized to moderate this room'
            ], 403);
        }

        // Prevent self-moderation
        if ($userId === $moderator->id) {
            return response()->json([
                'message' => 'You cannot ban yourself'
            ], 422);
        }

        $request->validate([
            'duration' => 'nullable|integer|min:1|max:525600', // Max 1 year in minutes
            'reason' => 'nullable|string|max:500',
        ]);

        $roomUser = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$roomUser) {
            return response()->json([
                'message' => 'User is not in this room'
            ], 404);
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

            return response()->json([
                'message' => 'User banned successfully',
                'action' => 'ban_user',
                'target_user_id' => $userId,
                'moderator' => $moderator->name,
                'duration_minutes' => $request->duration,
                'reason' => $request->reason,
                'banned_until' => $request->duration ? now()->addMinutes($request->duration)->toISOString() : null,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to ban user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unban a user from a room.
     */
    public function unbanUser(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $moderator = Auth::user();

        // Check if user can moderate
        if (!$moderator->canModerate($room)) {
            return response()->json([
                'message' => 'You are not authorized to moderate this room'
            ], 403);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $roomUser = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();

        if (!$roomUser) {
            return response()->json([
                'message' => 'User is not in this room'
            ], 404);
        }

        if (!$roomUser->isBanned()) {
            return response()->json([
                'message' => 'User is not banned'
            ], 422);
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

            return response()->json([
                'message' => 'User unbanned successfully',
                'action' => 'unban_user',
                'target_user_id' => $userId,
                'moderator' => $moderator->name,
                'reason' => $request->reason,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to unban user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get moderation actions for a room.
     */
    public function getModerationActions(Request $request, int $roomId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $moderator = Auth::user();

        // Check if user can moderate
        if (!$moderator->canModerate($room)) {
            return response()->json([
                'message' => 'You are not authorized to view moderation actions for this room'
            ], 403);
        }

        $perPage = $request->get('per_page', 20);
        $actionType = $request->get('action_type');
        $targetUserId = $request->get('target_user_id');

        $query = ChatModerationAction::forRoom($roomId)
            ->with(['moderator:id,name,email', 'targetUser:id,name,email', 'message:id,message'])
            ->orderBy('created_at', 'desc');

        if ($actionType) {
            $query->ofType($actionType);
        }

        if ($targetUserId) {
            $query->onUser($targetUserId);
        }

        $actions = $query->paginate($perPage);

        return response()->json([
            'moderation_actions' => $actions->items(),
            'pagination' => [
                'current_page' => $actions->currentPage(),
                'last_page' => $actions->lastPage(),
                'per_page' => $actions->perPage(),
                'total' => $actions->total(),
                'has_more_pages' => $actions->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get user's moderation status in a room.
     */
    public function getUserModerationStatus(Request $request, int $roomId, int $userId): JsonResponse
    {
        $room = ChatRoom::active()->findOrFail($roomId);
        $moderator = Auth::user();

        // Check if user can moderate
        if (!$moderator->canModerate($room)) {
            return response()->json([
                'message' => 'You are not authorized to view moderation status for this room'
            ], 403);
        }

        $roomUser = ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->with(['user:id,name,email', 'mutedByUser:id,name', 'bannedByUser:id,name'])
            ->first();

        if (!$roomUser) {
            return response()->json([
                'message' => 'User is not in this room'
            ], 404);
        }

        return response()->json([
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
        ]);
    }
}