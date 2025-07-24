<?php

use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Thing\CategoryController;
use App\Http\Controllers\Api\Thing\TagController;
use Illuminate\Support\Facades\Route;

// 物品
Route::prefix('things')->name('things.')->group(function () {
    Route::apiResource('items', ItemController::class);
    Route::get('search', [ItemController::class, 'search'])->name('items.search');
    
    // 分类
    Route::apiResource('categories', CategoryController::class);
    
    // 标签
    Route::apiResource('tags', TagController::class);
});