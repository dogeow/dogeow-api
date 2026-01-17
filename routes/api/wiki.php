<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WikiController;
use App\Http\Middleware\EnsureUserIsAdmin;

// 公开路由
Route::get('/', [WikiController::class, 'index']);
Route::get('/article/{slug}', [WikiController::class, 'getArticle']);
Route::get('/articles', [WikiController::class, 'getAllArticles']);

// 管理员路由
Route::middleware(['auth:sanctum', EnsureUserIsAdmin::class])->group(function () {
    // 节点管理
    Route::post('/nodes', [WikiController::class, 'storeNode']);
    Route::put('/nodes/{id}', [WikiController::class, 'updateNode']);
    Route::delete('/nodes/{id}', [WikiController::class, 'destroyNode']);
    
    // 链接管理
    Route::post('/links', [WikiController::class, 'storeLink']);
    Route::delete('/links/{id}', [WikiController::class, 'destroyLink']);
});

