<?php

use App\Http\Controllers\Api\Thing\LocationController;
use Illuminate\Support\Facades\Route;

// 树形结构的位置数据
Route::get('locations/tree', [LocationController::class, 'locationTree']);

// 区域
Route::get('areas', [LocationController::class, 'areaIndex']);
Route::post('areas', [LocationController::class, 'areaStore']);
Route::get('areas/{area}', [LocationController::class, 'areaShow']);
Route::put('areas/{area}', [LocationController::class, 'areaUpdate']);
Route::delete('areas/{area}', [LocationController::class, 'areaDestroy']);
Route::get('areas/{area}/rooms', [LocationController::class, 'areaRooms']);

// 房间
Route::get('rooms', [LocationController::class, 'roomIndex']);
Route::post('rooms', [LocationController::class, 'roomStore']);
Route::get('rooms/{room}', [LocationController::class, 'roomShow']);
Route::put('rooms/{room}', [LocationController::class, 'roomUpdate']);
Route::delete('rooms/{room}', [LocationController::class, 'roomDestroy']);
Route::get('rooms/{room}/spots', [LocationController::class, 'roomSpots']);

// 具体位置
Route::get('spots', [LocationController::class, 'spotIndex']);
Route::post('spots', [LocationController::class, 'spotStore']);
Route::get('spots/{spot}', [LocationController::class, 'spotShow']);
Route::put('spots/{spot}', [LocationController::class, 'spotUpdate']);
Route::delete('spots/{spot}', [LocationController::class, 'spotDestroy']); 