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
    }
} 