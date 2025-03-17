<?php

use App\Http\Controllers\Api\Thing\StatsController;
use Illuminate\Support\Facades\Route;

// 统计
Route::get('/statistics', [StatsController::class, 'index']);