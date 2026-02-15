<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Http\JsonResponse;

class CompendiumController extends Controller
{
    /**
     * 获取物品图鉴
     */
    public function items(): JsonResponse
    {
        $items = GameItemDefinition::where('is_active', true)
            ->orderBy('type')
            ->orderBy('required_level')
            ->get();

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * 获取怪物图鉴
     */
    public function monsters(): JsonResponse
    {
        $monsters = GameMonsterDefinition::where('is_active', true)
            ->orderBy('level')
            ->get();

        return response()->json([
            'monsters' => $monsters,
        ]);
    }

    /**
     * 获取怪物掉落表
     */
    public function monsterDrops(int $monster): JsonResponse
    {
        $monster = GameMonsterDefinition::where('is_active', true)
            ->findOrFail($monster);

        $dropTable = $monster->drop_table ?? [];

        // 解析掉落表中的物品类型
        $itemTypes = $dropTable['item_types'] ?? ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'ring', 'amulet'];

        // 获取对应类型的物品定义
        $items = GameItemDefinition::where('is_active', true)
            ->whereIn('type', $itemTypes)
            ->where('required_level', '<=', $monster->level + 3)
            ->orderBy('required_level')
            ->limit(20)
            ->get();

        return response()->json([
            'monster' => $monster,
            'drop_table' => $dropTable,
            'possible_items' => $items,
        ]);
    }
}
