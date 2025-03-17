<?php

use App\Http\Controllers\Api\Thing\NavController;
use Illuminate\Support\Facades\Route;

// 导航
Route::apiResource('navs', NavController::class);
Route::get('nav-categories', [NavController::class, 'categories']); 