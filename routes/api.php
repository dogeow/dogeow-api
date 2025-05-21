<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Nav\CategoryController;
use App\Http\Controllers\Api\WordController;
use App\Http\Controllers\Api\SearchController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // 引入各个项目的路由文件
    require base_path('routes/api/item.php');
    require base_path('routes/api/nav.php');
    require base_path('routes/api/note.php');
    require base_path('routes/api/location.php');
    require base_path('routes/api/todo.php');
    require base_path('routes/api/game.php');
    require base_path('routes/api/cloud.php');
    require base_path('routes/api/word.php');

    // 批量上传图片
    Route::post('/upload/images', [ItemController::class, 'uploadBatchImages']);
});

// 公开路由
Route::get('public-items', [App\Http\Controllers\Api\Thing\ItemController::class, 'index']);

// 物品搜索路由 - 使用控制器方法
Route::get('/things', [App\Http\Controllers\Api\Thing\ItemController::class, 'index']);

// 直接查询数据库的路由
Route::get('/db-search', [SearchController::class, 'dbSearch']);

// 简单搜索路由 - 直接使用 LIKE 查询
Route::get('/search', [SearchController::class, 'search']);

// 音乐相关路由
Route::prefix('musics')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MusicController::class, 'index']);
});