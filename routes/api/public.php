<?php

use Illuminate\Support\Facades\Route;

// Auth
Route::post('/login', [App\Http\Controllers\AuthController::class, 'login']);
Route::post('/register', [App\Http\Controllers\AuthController::class, 'register']);

// Client info
Route::get('/client-basic-info', [App\Http\Controllers\Api\ClientInfoController::class, 'getBasicInfo']);
Route::get('/client-info', [App\Http\Controllers\Api\ClientInfoController::class, 'getClientInfo']);
Route::get('/client-location-info', [App\Http\Controllers\Api\ClientInfoController::class, 'getLocationInfo']);

// Cloud
require base_path('routes/api/cloud.php');

// Musics
Route::prefix('musics')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MusicController::class, 'index']);
    Route::get('/{filename}', [App\Http\Controllers\Api\MusicController::class, 'download']);
});

// Public notes
Route::get('notes/article/{slug}', [\App\Http\Controllers\Api\NoteController::class, 'getArticleBySlug']);
Route::get('notes/wiki/articles', [\App\Http\Controllers\Api\NoteController::class, 'getAllWikiArticles']);

// Public nav/tools
require base_path('routes/api/nav.php');
require base_path('routes/api/tools.php');
