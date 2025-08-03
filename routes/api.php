<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\UploadController;

// 公开路由

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Debug 路由
Route::post('/debug/log-error', [App\Http\Controllers\Api\DebugController::class, 'logError']);

// 广播认证路由 - 支持公共和私有频道
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    $channelName = $request->input('channel_name');
    
    // 如果是公共频道（不以 private- 或 presence- 开头），允许访问
    if (!str_starts_with($channelName, 'private-') && !str_starts_with($channelName, 'presence-')) {
        return response()->json([]);
    }
    
    // 对于私有频道，需要认证
    if (!auth('sanctum')->check()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    
    return response()->json(['auth' => 'success']);
});
Route::get('/client-info', [App\Http\Controllers\Api\ClientInfoController::class, 'getClientInfo']);
Route::prefix('musics')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MusicController::class, 'index']);
});
require base_path('routes/api/cloud.php');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [AuthController::class, 'update']);
    
    // WebSocket authentication test route
    Route::middleware('websocket.auth')->get('/websocket-test', function () {
        return response()->json([
            'message' => 'WebSocket authentication successful',
            'user' => auth()->user()->only(['id', 'name', 'email'])
        ]);
    });
    
    // 批量上传图片
    Route::post('/upload/images', [UploadController::class, 'uploadBatchImages']);
    
    // 引入各个项目的路由文件
    require base_path('routes/api/item.php');
    require base_path('routes/api/location.php');
    require base_path('routes/api/note.php');
    require base_path('routes/api/todo.php');
    require base_path('routes/api/game.php');
    require base_path('routes/api/chat.php');
    require base_path('routes/api/profile.php');

});

// 公开的导航路由
require base_path('routes/api/nav.php');

// 公开的工具路由
require base_path('routes/api/tools.php');