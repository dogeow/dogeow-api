<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\Spot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * 获取物品统计信息
     */
    public function index()
    {
        // 物品总数
        $totalItems = Item::where('user_id', Auth::id())->count();
        
        // 物品总价值
        $totalValue = Item::where('user_id', Auth::id())
            ->sum(DB::raw('purchase_price * quantity'));
        
        // 按分类统计
        $categoryStats = ItemCategory::where('user_id', Auth::id())
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->withSum(DB::raw('items'), DB::raw('purchase_price * quantity'))
            ->get();
        
        // 按位置统计
        $spotStats = Spot::where('user_id', Auth::id())
            ->withCount('items')
            ->withSum('items', 'quantity')
            ->withSum(DB::raw('items'), DB::raw('purchase_price * quantity'))
            ->with('room.area')
            ->get();
        
        // 即将过期的物品
        $expiringItems = Item::where('user_id', Auth::id())
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->orderBy('expiry_date')
            ->with(['category', 'spot.room.area'])
            ->get();
        
        // 已过期的物品
        $expiredItems = Item::where('user_id', Auth::id())
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->orderBy('expiry_date', 'desc')
            ->with(['category', 'spot.room.area'])
            ->get();
        
        return response()->json([
            'total_items' => $totalItems,
            'total_value' => $totalValue,
            'category_stats' => $categoryStats,
            'spot_stats' => $spotStats,
            'expiring_items' => $expiringItems,
            'expired_items' => $expiredItems,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
