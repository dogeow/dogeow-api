<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Chat room channels for real-time messaging
Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {
    // Users can join any chat room if they are authenticated
    // Additional room-specific authorization can be added here
    return [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
});

// Private user channels for notifications
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
