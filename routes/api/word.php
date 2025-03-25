<?php

use App\Http\Controllers\Api\Word\CategoryController;
use App\Http\Controllers\Api\Word\BookController;
use App\Http\Controllers\Api\Word\WordController;
use App\Http\Controllers\Api\Word\UserWordController;
use Illuminate\Support\Facades\Route;

// 单词分类路由
Route::prefix('word')->group(function () {
    // 公开路由
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{book}', [BookController::class, 'show']);
    Route::get('/books/{book}/words', [BookController::class, 'words']);
    Route::get('/words', [WordController::class, 'index']);
    Route::get('/words/{word}', [WordController::class, 'show']);
    Route::get('/words/random', [WordController::class, 'random']);
    
    // 需要认证的路由
    Route::middleware('auth:sanctum')->group(function () {
        // 管理路由
        Route::get('/admin/categories', [CategoryController::class, 'all']);
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{book}', [BookController::class, 'update']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);
        Route::post('/books/{book}/update-total', [BookController::class, 'updateTotalWords']);
        
        Route::post('/words', [WordController::class, 'store']);
        Route::post('/words/batch', [WordController::class, 'batchStore']);
        Route::put('/words/{word}', [WordController::class, 'update']);
        Route::delete('/words/{word}', [WordController::class, 'destroy']);
        
        // 用户学习记录路由
        Route::get('/user/words', [UserWordController::class, 'index']);
        Route::post('/user/words', [UserWordController::class, 'store']);
        Route::get('/user/words/{word}', [UserWordController::class, 'show']);
        Route::put('/user/words/{word}', [UserWordController::class, 'update']);
        Route::post('/user/words/{word}/favorite', [UserWordController::class, 'toggleFavorite']);
        Route::post('/user/words/{word}/status', [UserWordController::class, 'updateStatus']);
        Route::post('/user/words/{word}/review', [UserWordController::class, 'recordReview']);
        Route::get('/user/favorites', [UserWordController::class, 'favorites']);
    });
}); 