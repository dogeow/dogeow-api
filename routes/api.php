<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Nav\CategoryController;
use App\Http\Controllers\Api\Nav\ItemController as NavItemController;
use App\Http\Controllers\Api\WordController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\Cloud\FileController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // 引入各个项目的路由文件
    require base_path('routes/api/item.php');
    require base_path('routes/api/location.php');
    require base_path('routes/api/stats.php');
    require base_path('routes/api/todo.php');
    require base_path('routes/api/game.php');
    // 导航管理相关路由需要认证
    Route::prefix('nav')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::get('/admin/categories', [CategoryController::class, 'all']);
        
        Route::post('/items', [NavItemController::class, 'store']);
        Route::put('/items/{item}', [NavItemController::class, 'update']);
        Route::delete('/items/{item}', [NavItemController::class, 'destroy']);
    });

    // 笔记相关路由
    Route::apiResource('notes', \App\Http\Controllers\Api\NoteController::class);
    Route::apiResource('note-tags', \App\Http\Controllers\Api\NoteTagController::class);
    Route::apiResource('note-categories', \App\Http\Controllers\Api\NoteCategoryController::class);
    
    // 批量上传图片
    Route::post('/upload/images', [ItemController::class, 'uploadBatchImages']);
});

// Cloud Files - 云存储文件路由（公开路由）
Route::get('/cloud/files', [FileController::class, 'index']);
Route::get('/cloud/files/{id}', [FileController::class, 'show']);
Route::post('/cloud/folders', [FileController::class, 'createFolder']);
Route::post('/cloud/files', [FileController::class, 'upload']);
Route::get('/cloud/files/{id}/download', [FileController::class, 'download'])->name('cloud.files.download');
Route::get('/cloud/files/{id}/preview', [FileController::class, 'preview']);
Route::delete('/cloud/files/{id}', [FileController::class, 'destroy']);
Route::put('/cloud/files/{id}', [FileController::class, 'update']);
Route::post('/cloud/files/move', [FileController::class, 'move']);
Route::get('/cloud/tree', [FileController::class, 'tree']);
Route::get('/cloud/statistics', [FileController::class, 'statistics']);

// 公开路由
Route::get('public-items', [App\Http\Controllers\Api\Thing\ItemController::class, 'index']);

// 导航查询相关公开路由
Route::prefix('nav')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/items', [NavItemController::class, 'index']);
    Route::get('/items/{item}', [NavItemController::class, 'show']);
    Route::post('/items/{item}/click', [NavItemController::class, 'recordClick']);
});

// 引入单词相关路由
require base_path('routes/api/word.php');

// 添加测试路由
Route::get('/test-word-categories', [WordController::class, 'testCategories']);

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