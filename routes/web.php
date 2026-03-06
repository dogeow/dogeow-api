<?php

use Illuminate\Support\Facades\Route;

// 广播认证路由 - 支持公共和私有频道
require base_path('routes/api/broadcast.php');

Route::get('/', function () {
    return view('welcome');
});

// Provide a named login route to avoid RouteNotFoundException when
// unauthenticated requests trigger redirects to the 'login' route.
// For API clients we return a JSON 401 response.
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
