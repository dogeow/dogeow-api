<?php

use Illuminate\Support\Facades\Route;

// 待办事项
Route::apiResource('todos', 'App\Http\Controllers\Api\TodoController'); 