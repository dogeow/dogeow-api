<?php

use Illuminate\Support\Facades\Route;

// 笔记相关路由
Route::prefix('notes')->group(function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('/', \App\Http\Controllers\Api\NoteController::class);
        Route::apiResource('tags', \App\Http\Controllers\Api\NoteTagController::class);
        Route::apiResource('categories', \App\Http\Controllers\Api\NoteCategoryController::class);
    });
});