<?php

use Illuminate\Support\Facades\Route;

// 区域
Route::get('areas', 'App\Http\Controllers\Api\LocationController@areaIndex');
Route::post('areas', 'App\Http\Controllers\Api\LocationController@areaStore');
Route::get('areas/{area}', 'App\Http\Controllers\Api\LocationController@areaShow');
Route::put('areas/{area}', 'App\Http\Controllers\Api\LocationController@areaUpdate');
Route::delete('areas/{area}', 'App\Http\Controllers\Api\LocationController@areaDestroy');

// 房间
Route::get('rooms', 'App\Http\Controllers\Api\LocationController@roomIndex');
Route::post('rooms', 'App\Http\Controllers\Api\LocationController@roomStore');
Route::get('rooms/{room}', 'App\Http\Controllers\Api\LocationController@roomShow');
Route::put('rooms/{room}', 'App\Http\Controllers\Api\LocationController@roomUpdate');
Route::delete('rooms/{room}', 'App\Http\Controllers\Api\LocationController@roomDestroy');

// 具体位置
Route::get('spots', 'App\Http\Controllers\Api\LocationController@spotIndex');
Route::post('spots', 'App\Http\Controllers\Api\LocationController@spotStore');
Route::get('spots/{spot}', 'App\Http\Controllers\Api\LocationController@spotShow');
Route::put('spots/{spot}', 'App\Http\Controllers\Api\LocationController@spotUpdate');
Route::delete('spots/{spot}', 'App\Http\Controllers\Api\LocationController@spotDestroy'); 