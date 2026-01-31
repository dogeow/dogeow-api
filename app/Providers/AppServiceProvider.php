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

        // Spatie\JsonApiPaginate 已作为正式依赖安装，移除 shim 回退
        // 如果将来需要本地回退，请在单独的测试-helper 中提供，而不要在生产 AppServiceProvider 中使用 eval。
    }
}
