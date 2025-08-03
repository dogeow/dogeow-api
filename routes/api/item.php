<?php

use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Thing\CategoryController;
use App\Http\Controllers\Api\Thing\TagController;
use App\Http\Controllers\Api\Thing\NavController;
use App\Http\Controllers\Api\Thing\GameController;
use App\Http\Controllers\Api\Thing\TodoController;
use Illuminate\Support\Facades\Route;

// 物品
Route::prefix('things')->name('things.')->group(function () {
    Route::apiResource('items', ItemController::class);
    Route::get('search', [ItemController::class, 'search'])->name('items.search');
    
    // 分类
    Route::apiResource('categories', CategoryController::class);
    
    // 标签
    Route::apiResource('tags', TagController::class);
    
    // 导航
    Route::get('nav/categories', [NavController::class, 'categories'])->name('nav.categories');
    Route::apiResource('nav', NavController::class);
    
    // 游戏
    Route::get('games/{id}/play', [GameController::class, 'play'])->name('games.play');
    Route::apiResource('games', GameController::class);
    
    // 待办事项
    Route::apiResource('todos', TodoController::class);
});