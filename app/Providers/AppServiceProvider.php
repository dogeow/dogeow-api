<?php

namespace App\Providers;

use App\Events\WebSocketDisconnected;
use App\Listeners\WebSocketDisconnectListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 注册 WebSocket 断开连接事件监听器
        Event::listen(WebSocketDisconnected::class, WebSocketDisconnectListener::class);
    }
}
