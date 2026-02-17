<?php

namespace App\Http\Controllers\Api\Chat;

use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

trait ChatControllerHelpers
{
    /**
     * Check if user is in room
     */
    protected function isUserInRoom(int $roomId, int $userId): bool
    {
        return ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get room user info
     */
    protected function fetchRoomUser(int $roomId, int $userId): ?ChatRoomUser
    {
        return ChatRoomUser::where('room_id', $roomId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Invalidate related cache
     */
    protected function clearRoomCache(int $roomId): void
    {
        $this->cacheService->invalidateOnlineUsers($roomId);
        $this->cacheService->invalidateRoomStats($roomId);
        $this->cacheService->invalidateRoomList();
    }

    /**
     * Clear room cache and log activity
     */
    protected function clearCacheAndLogActivity(int $roomId, string $action, int $userId): void
    {
        $this->clearRoomCache($roomId);
        $this->logRoomActivity($roomId, $action, $userId);
    }

    /**
     * Log room activity
     */
    protected function logRoomActivity(int $roomId, string $action, int $userId): void
    {
        $this->chatService->trackRoomActivity($roomId, $action, $userId);
    }

    /**
     * Unified error logging and response
     */
    protected function logAndError(string $logMessage, \Throwable $e, array $context, string $userMessage, int $statusCode = 500): JsonResponse
    {
        Log::error($logMessage, array_merge($context, [
            'error' => $e->getMessage(),
        ]));

        return $this->error($userMessage, [], $statusCode);
    }

    /**
     * Normalize room ID
     */
    protected function normalizeRoomId($roomId): int
    {
        return (int) $roomId;
    }

    /**
     * Get active room or throw 404
     */
    protected function findActiveRoom(int $roomId): ChatRoom
    {
        return ChatRoom::active()->findOrFail($roomId);
    }

    /**
     * Ensure user has joined the room
     */
    protected function ensureUserInRoom(int $roomId, int $userId, string $message, int $statusCode = 403): ?JsonResponse
    {
        if (! $this->isUserInRoom($roomId, $userId)) {
            return $this->error($message, [], $statusCode);
        }

        return null;
    }
}
