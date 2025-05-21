<?php

use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Thing\CategoryController;
use App\Http\Controllers\Api\Thing\TagController;
use Illuminate\Support\Facades\Route;

// 物品
Route::apiResource('thing-items', ItemController::class);

// 分类
Route::apiResource('categories', CategoryController::class);

// 标签
Route::apiResource('thing-tags', TagController::class);

// 获取用户的物品分类
Route::get('user/categories', [ItemController::class, 'categories']);

// 临时图片上传
Route::post('thing-items/upload-temp-image', [ItemController::class, 'uploadTempImage']);