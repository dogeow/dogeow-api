<?php

use App\Http\Controllers\Api\Tools\TitleController;
use Illuminate\Support\Facades\Route;

Route::get('/fetch-title', [TitleController::class, 'fetch']);
