<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\UploadController;

// 公开路由
require base_path('routes/api/public.php');
require base_path('routes/api/broadcast.php');

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
    require base_path('routes/api/chat.php');
    require base_path('routes/api/game.php');
    require base_path('routes/api/home.php');
    require base_path('routes/api/item.php');
    require base_path('routes/api/location.php');
    require base_path('routes/api/note.php');
    require base_path('routes/api/profile.php');
    require base_path('routes/api/todo.php');
    require base_path('routes/api/word.php');

});
