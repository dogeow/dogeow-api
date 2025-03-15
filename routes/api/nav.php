<?php

use Illuminate\Support\Facades\Route;

// 导航
Route::apiResource('navs', 'App\Http\Controllers\Api\NavController');
Route::get('nav-categories', 'App\Http\Controllers\Api\NavController@categories'); 