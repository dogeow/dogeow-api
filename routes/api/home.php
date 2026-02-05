<?php

use App\Http\Controllers\Api\HomeLayoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('home')->name('home.')->group(function () {
    Route::get('layout', [HomeLayoutController::class, 'show']);
    Route::put('layout', [HomeLayoutController::class, 'update']);
});
