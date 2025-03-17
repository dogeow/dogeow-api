<?php

use App\Http\Controllers\Api\Thing\GameController;
use Illuminate\Support\Facades\Route;

// 游戏
Route::apiResource('games', GameController::class);
Route::get('games/{game}/play', [GameController::class, 'play']); 