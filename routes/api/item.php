<?php

use Illuminate\Support\Facades\Route;

// 物品
Route::apiResource('items', 'App\Http\Controllers\Api\Thing\ItemController');
Route::get('categories', 'App\Http\Controllers\Api\Thing\ItemController@categories');

// 分类
Route::apiResource('item-categories', 'App\Http\Controllers\Api\Thing\CategoryController'); 