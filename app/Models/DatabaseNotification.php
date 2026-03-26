<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

class DatabaseNotification extends BaseDatabaseNotification
{
    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (self $notification) {
            if (empty($notification->id)) {
                $notification->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
