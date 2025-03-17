<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\Thing\ItemController;
use App\Models\Thing\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
    require base_path('routes/api/nav.php');
});

// 公开路由
Route::get('public-items', [App\Http\Controllers\Api\Thing\ItemController::class, 'index']);

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