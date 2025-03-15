<?php

use Illuminate\Support\Facades\Route;

// 统计
Route::get('stats', 'App\Http\Controllers\Api\StatsController@index'); 