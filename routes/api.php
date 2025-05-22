<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\UploadController;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/db-search', [SearchController::class, 'dbSearch']);

// 音乐相关路由
Route::prefix('musics')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MusicController::class, 'index']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // 引入各个项目的路由文件
    require base_path('routes/api/item.php');
    require base_path('routes/api/location.php');

    require base_path('routes/api/nav.php');
    require base_path('routes/api/note.php');
    require base_path('routes/api/todo.php');
    require base_path('routes/api/game.php');
    require base_path('routes/api/cloud.php');
    require base_path('routes/api/word.php');

    // 批量上传图片
    Route::post('/upload/images', [UploadController::class, 'uploadBatchImages']);
});