<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\Thing\ItemController;
use App\Http\Controllers\Api\Nav\CategoryController;
use App\Http\Controllers\Api\Nav\ItemController as NavItemController;
use App\Models\Thing\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Api\Cloud\FileController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // 引入各个项目的路由文件
    require base_path('routes/api/item.php');
    require base_path('routes/api/location.php');
    require base_path('routes/api/stats.php');
    require base_path('routes/api/todo.php');
    require base_path('routes/api/game.php');
    // 导航管理相关路由需要认证
    Route::prefix('nav')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::get('/admin/categories', [CategoryController::class, 'all']);
        
        Route::post('/items', [NavItemController::class, 'store']);
        Route::put('/items/{item}', [NavItemController::class, 'update']);
        Route::delete('/items/{item}', [NavItemController::class, 'destroy']);
    });

    // 笔记相关路由
    Route::apiResource('notes', \App\Http\Controllers\Api\NoteController::class);
    Route::apiResource('note-tags', \App\Http\Controllers\Api\NoteTagController::class);
    Route::apiResource('note-categories', \App\Http\Controllers\Api\NoteCategoryController::class);
});

// Cloud Files - 云存储文件路由（公开路由）
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

// 公开路由
Route::get('public-items', [App\Http\Controllers\Api\Thing\ItemController::class, 'index']);

// 导航查询相关公开路由
Route::prefix('nav')->group(function () {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/items', [NavItemController::class, 'index']);
    Route::get('/items/{item}', [NavItemController::class, 'show']);
    Route::post('/items/{item}/click', [NavItemController::class, 'recordClick']);
});

// 引入单词相关路由
require base_path('routes/api/word.php');

// 添加测试路由
Route::get('/test-word-categories', function() {
    $tableName = 'word_categories';
    
    // 尝试直接从数据库查询
    $categories = DB::table($tableName)->get();
    
    // 检查表是否存在
    $tableExists = Schema::hasTable($tableName);
    
    // 查看表结构
    $columns = [];
    if ($tableExists) {
        $columns = DB::getSchemaBuilder()->getColumnListing($tableName);
    }
    
    return [
        'table_exists' => $tableExists,
        'table_name' => $tableName,
        'columns' => $columns,
        'count' => $categories->count(),
        'data' => $categories
    ];
});

// 物品搜索路由 - 使用控制器方法
Route::get('/things', [App\Http\Controllers\Api\Thing\ItemController::class, 'index']);

// 直接查询数据库的路由
Route::get('/db-search', function (Request $request) {
    $search = $request->get('q', '');
    
    // 直接使用 DB 查询构建器
    $results = DB::table('thing_items')
        ->where('name', 'like', "%{$search}%")
        ->orWhere('description', 'like', "%{$search}%")
        ->limit(10)
        ->get();
    
    return response()->json([
        'search_term' => $search,
        'count' => $results->count(),
        'results' => $results
    ]);
});

// 简单搜索路由 - 直接使用 LIKE 查询
Route::get('/search', function (Request $request) {
    $search = $request->get('q', '');
    
    // 确认表名
    $tableName = (new Item())->getTable();
    
    // 不带任何条件的查询，获取所有记录
    $allRecords = Item::limit(10)->get();
    
    // 只带搜索条件的查询，不带权限过滤
    $searchOnlyResults = Item::where(function($query) use ($search) {
        $query->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
    })->get();
    
    // 完整的搜索查询
    $results = Item::where(function($query) use ($search) {
        $query->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
    })
    ->where(function($query) {
        // 如果用户已登录，显示公开物品和自己的物品
        if (Auth::check()) {
            $query->where('is_public', true)
                  ->orWhere('user_id', Auth::id());
        } else {
            // 未登录用户只能看到公开物品
            $query->where('is_public', true);
        }
    })
    ->with(['category'])
    ->get();
    
    return response()->json([
        'search_term' => $search,
        'table_name' => $tableName,
        'all_records_count' => $allRecords->count(),
        'all_records' => $allRecords,
        'search_only_count' => $searchOnlyResults->count(),
        'search_only_results' => $searchOnlyResults,
        'final_count' => $results->count(),
        'final_results' => $results
    ]);
});

// 音乐相关路由
Route::prefix('music')->group(function () {
    Route::get('/', [App\Http\Controllers\Api\MusicController::class, 'index']);
    Route::get('/stream/{filename}', [App\Http\Controllers\Api\MusicController::class, 'stream'])->where('filename', '.*');

    // HLS 音乐路由
    Route::prefix('hls')->group(function () {
        Route::get('/', [App\Http\Controllers\Api\HLSMusicController::class, 'index']);
        Route::get('/stream/{path}', [App\Http\Controllers\Api\HLSMusicController::class, 'stream'])->where('path', '.*');
        Route::post('/generate', [App\Http\Controllers\Api\HLSMusicController::class, 'generateHLS']);
    });
}); 