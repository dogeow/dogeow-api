<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TitleController;

Route::get('/fetch-title', [TitleController::class, 'fetch']);