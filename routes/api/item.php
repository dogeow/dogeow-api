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
    
    // 搜索相关路由
    Route::get('search', [ItemController::class, 'search'])->name('items.search');
    Route::get('search/suggestions', [ItemController::class, 'searchSuggestions'])->name('items.search.suggestions');
    Route::get('search/history', [ItemController::class, 'searchHistory'])->name('items.search.history');
    Route::delete('search/history', [ItemController::class, 'clearSearchHistory'])->name('items.search.history.clear');
    
    // 物品关联路由
    Route::get('items/{item}/relations', [ItemController::class, 'relations'])->name('items.relations');
    Route::post('items/{item}/relations', [ItemController::class, 'addRelation'])->name('items.relations.add');
    Route::delete('items/{item}/relations/{relatedItemId}', [ItemController::class, 'removeRelation'])->name('items.relations.remove');
    Route::post('items/{item}/relations/batch', [ItemController::class, 'batchAddRelations'])->name('items.relations.batch');
    
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