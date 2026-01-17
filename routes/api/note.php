<?php

use Illuminate\Support\Facades\Route;

// 笔记相关路由
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('notes/tags', \App\Http\Controllers\Api\NoteTagController::class);
    Route::apiResource('notes/categories', \App\Http\Controllers\Api\NoteCategoryController::class);
    
    // 图谱相关路由（必须在 apiResource 之前定义，避免被当作资源 ID）
    Route::get('notes/graph', [\App\Http\Controllers\Api\NoteController::class, 'getGraph']);
    Route::post('notes/links', [\App\Http\Controllers\Api\NoteController::class, 'storeLink']);
    Route::delete('notes/links/{id}', [\App\Http\Controllers\Api\NoteController::class, 'destroyLink']);
    
    Route::apiResource('notes', \App\Http\Controllers\Api\NoteController::class);
});

// 公开路由：通过 slug 获取文章
Route::get('notes/article/{slug}', [\App\Http\Controllers\Api\NoteController::class, 'getArticleBySlug']);