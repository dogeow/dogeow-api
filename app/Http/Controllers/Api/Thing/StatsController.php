<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\Spot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            ->sum(DB::raw('COALESCE(purchase_price, 0) * quantity'));
        
        // 按分类统计
        $byCategory = ItemCategory::where('user_id', Auth::id())
            ->withCount('items')
            ->with('items')
            ->get()
            ->map(function ($category) {
                $value = $category->items->sum(function ($item) {
                    return ($item->purchase_price ?? 0) * $item->quantity;
                });
                
                return [
                    'name' => $category->name,
                    'count' => $category->items_count,
                    'value' => $value
                ];
            });
        
        // 按状态统计
        $byStatus = [
            [
                'status' => 'active',
                'count' => Item::where('user_id', Auth::id())
                    ->where('status', 'active')
                    ->count(),
                'value' => Item::where('user_id', Auth::id())
                    ->where('status', 'active')
                    ->sum(DB::raw('COALESCE(purchase_price, 0) * quantity'))
            ],
            [
                'status' => 'inactive',
                'count' => Item::where('user_id', Auth::id())
                    ->where('status', 'inactive')
                    ->count(),
                'value' => Item::where('user_id', Auth::id())
                    ->where('status', 'inactive')
                    ->sum(DB::raw('COALESCE(purchase_price, 0) * quantity'))
            ],
            [
                'status' => 'expired',
                'count' => Item::where('user_id', Auth::id())
                    ->where('status', 'expired')
                    ->count(),
                'value' => Item::where('user_id', Auth::id())
                    ->where('status', 'expired')
                    ->sum(DB::raw('COALESCE(purchase_price, 0) * quantity'))
            ]
        ];
        
        // 按位置统计
        $byLocation = Spot::where('user_id', Auth::id())
            ->withCount('items')
            ->with(['items', 'room.area'])
            ->get()
            ->map(function ($spot) {
                $value = $spot->items->sum(function ($item) {
                    return ($item->purchase_price ?? 0) * $item->quantity;
                });
                
                $locationName = '';
                if ($spot->room && $spot->room->area) {
                    $locationName = $spot->room->area->name . ' > ' . $spot->room->name . ' > ' . $spot->name;
                } elseif ($spot->room) {
                    $locationName = $spot->room->name . ' > ' . $spot->name;
                } else {
                    $locationName = $spot->name;
                }
                
                return [
                    'name' => $locationName,
                    'count' => $spot->items_count,
                    'value' => $value
                ];
            });
        
        // 最近添加的物品
        $recentItems = Item::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'created_at']);
        
        // 即将过期的物品
        $expiringItems = Item::where('user_id', Auth::id())
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays(30))
            ->orderBy('expiry_date')
            ->limit(5)
            ->get(['id', 'name', 'expiry_date']);
        
        return response()->json([
            'totalItems' => $totalItems,
            'totalValue' => (float) $totalValue,
            'byCategory' => $byCategory,
            'byStatus' => $byStatus,
            'byLocation' => $byLocation,
            'recentItems' => $recentItems,
            'expiringItems' => $expiringItems,
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
