<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Thing\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function dbSearch(Request $request)
    {
        $search = $request->get('q', '');
        $user = Auth::user();
        
        // 构建查询，考虑权限控制
        $query = DB::table('thing_items')
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        
        // 权限控制：
        // 1. 如果用户已登录，可以看到自己的所有物品 + 其他人的公开物品
        // 2. 如果用户未登录，只能看到公开物品
        if ($user) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)  // 自己的物品
                  ->orWhere('is_public', true);   // 或者公开的物品
            });
        } else {
            // 未登录用户只能看到公开物品
            $query->where('is_public', true);
        }
        
        $results = $query->limit(10)->get();
        
        return response()->json([
            'search_term' => $search,
            'count' => $results->count(),
            'results' => $results,
            'user_authenticated' => !!$user
        ]);
    }
} 