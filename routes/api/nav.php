<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Nav\ItemController;
use App\Http\Controllers\TitleController;

// 这些路由已移动到 routes/api.php 文件中
// use App\Http\Controllers\Api\Nav\CategoryController;
// use App\Http\Controllers\Api\Nav\ItemController;

// // 导航分类
// Route::apiResource('nav/categories', CategoryController::class);
// Route::get('nav/admin/categories', [CategoryController::class, 'all']);

// // 导航项
// Route::apiResource('nav/items', ItemController::class);
// Route::post('nav/items/{item}/click', [ItemController::class, 'recordClick']);

Route::get('/fetch-title', [TitleController::class, 'fetch']);

Route::prefix('nav')->group(function () {
    Route::get('items', [ItemController::class, 'index'])->name('nav.items.index');
    Route::get('items/{item}', [ItemController::class, 'show'])->name('nav.items.show');
    Route::post('items', [ItemController::class, 'store'])->name('nav.items.store');
    Route::put('items/{item}', [ItemController::class, 'update'])->name('nav.items.update');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('nav.items.destroy');
    Route::post('items/{item}/click', [ItemController::class, 'recordClick'])->name('nav.items.click');
}); 

Route::middleware('auth:sanctum')->group(function () {
    // 导航管理相关路由需要认证
    Route::prefix('nav')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::get('/admin/categories', [CategoryController::class, 'all']);
    });
});