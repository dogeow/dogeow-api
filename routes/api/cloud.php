<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Cloud\FileController;

// 云存储
Route::get('/cloud/files', [FileController::class, 'index']);
Route::get('/cloud/files/{id}', [FileController::class, 'show']);
Route::post('/cloud/folders', [FileController::class, 'createFolder']);
Route::post('/cloud/files', [FileController::class, 'upload']);
Route::get('/cloud/files/{id}/download', [FileController::class, 'download'])->name('cloud.files.download');
Route::get('/cloud/files/{id}/preview', [FileController::class, 'preview']);
Route::delete('/cloud/files/{id}', [FileController::class, 'destroy']);
Route::put('/cloud/files/{id}', [FileController::class, 'update']);
Route::post('/cloud/files/move', [FileController::class, 'move']);
Route::get('/cloud/tree', [FileController::class, 'tree']);
Route::get('/cloud/statistics', [FileController::class, 'statistics']);