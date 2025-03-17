<?php

use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Thing\CategoryController;
use Illuminate\Support\Facades\Route;

// 物品
Route::apiResource('items', ItemController::class);

// 分类
Route::apiResource('categories', CategoryController::class);

// 获取用户的物品分类
Route::get('user/categories', [ItemController::class, 'categories']);