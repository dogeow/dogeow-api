<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // 笔记相关路由
    Route::apiResource('notes', \App\Http\Controllers\Api\NoteController::class);
    Route::apiResource('note-tags', \App\Http\Controllers\Api\NoteTagController::class);
    Route::apiResource('note-categories', \App\Http\Controllers\Api\NoteCategoryController::class);
});