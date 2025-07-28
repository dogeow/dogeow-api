<?php

namespace App\Providers;

use Illuminate\Broadcasting\BroadcastServiceProvider as LaravelBroadcastServiceProvider;

class BroadcastServiceProvider extends LaravelBroadcastServiceProvider
{
    public function boot()
    {
        require base_path('routes/channels.php');
    }
} 