<?php

use Illuminate\Support\Facades\Route;

// 笔记相关路由
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('notes/tags', \App\Http\Controllers\Api\NoteTagController::class);
    Route::apiResource('notes/categories', \App\Http\Controllers\Api\NoteCategoryController::class);
    Route::apiResource('notes', \App\Http\Controllers\Api\NoteController::class);
});