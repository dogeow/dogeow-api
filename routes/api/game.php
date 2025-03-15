<?php

use Illuminate\Support\Facades\Route;

// 游戏
Route::apiResource('games', 'App\Http\Controllers\Api\GameController');
Route::get('games/{game}/play', 'App\Http\Controllers\Api\GameController@play'); 