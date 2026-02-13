<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * 获取商店物品列表
     */
    public function index(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        // 获取所有可购买的物品定义
        $items = GameItemDefinition::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('required_level')
            ->get();

        // 为每个物品计算价格
        $shopItems = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type,
                'sub_type' => $item->sub_type,
                'base_stats' => $item->base_stats,
                'required_level' => $item->required_level,
                'required_strength' => $item->required_strength,
                'required_dexterity' => $item->required_dexterity,
                'required_energy' => $item->required_energy,
                'icon' => $item->icon,
                'description' => $item->description,
                'buy_price' => $this->calculateBuyPrice($item),
                'sell_price' => $this->calculateSellPrice($item),
            ];
        });

        return $this->success([
            'items' => $shopItems,
            'player_gold' => $character->gold,
        ]);
    }

    /**
     * 购买物品
     */
    public function buy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_item_definitions,id',
            'quantity' => 'sometimes|integer|min:1|max:99',
        ]);

        $character = $this->getCharacter($request);
        $quantity = $validated['quantity'] ?? 1;

        $definition = GameItemDefinition::find($validated['item_id']);

        if (! $definition || ! $definition->is_active) {
            return $this->error('物品不存在或不可购买');
        }

        // 检查等级要求
        if ($character->level < $definition->required_level) {
            return $this->error("需要等级 {$definition->required_level}");
        }

        // 计算总价
        $totalPrice = $this->calculateBuyPrice($definition) * $quantity;

        // 检查金币是否足够
        if ($character->gold < $totalPrice) {
            return $this->error("金币不足，需要 {$totalPrice} 金币");
        }

        // 检查背包空间
        $inventoryCount = $character->items()->where('is_in_storage', false)->count();
        $inventorySize = InventoryController::INVENTORY_SIZE;

        // 对于可堆叠物品（药品），检查是否已有相同物品
        if ($definition->type === 'potion') {
            $existingItem = $character->items()
                ->where('definition_id', $definition->id)
                ->where('is_in_storage', false)
                ->where('quality', 'common')
                ->first();

            if ($existingItem) {
                // 增加数量
                $existingItem->quantity += $quantity;
                $existingItem->save();
            } else {
                // 检查背包空间
                if ($inventoryCount >= $inventorySize) {
                    return $this->error('背包已满');
                }

                // 创建新物品
                GameItem::create([
                    'character_id' => $character->id,
                    'definition_id' => $definition->id,
                    'quality' => 'common',
                    'stats' => $definition->base_stats,
                    'affixes' => [],
                    'is_in_storage' => false,
                    'quantity' => $quantity,
                    'slot_index' => $this->findEmptySlot($character, false),
                ]);
            }
        } else {
            // 装备类物品，每个占用一个格子
            if ($inventoryCount + $quantity > $inventorySize) {
                return $this->error('背包空间不足');
            }

            // 为每个装备创建单独的物品实例
            for ($i = 0; $i < $quantity; $i++) {
                // 商店购买的装备品质为普通
                GameItem::create([
                    'character_id' => $character->id,
                    'definition_id' => $definition->id,
                    'quality' => 'common',
                    'stats' => $definition->base_stats,
                    'affixes' => [],
                    'is_in_storage' => false,
                    'quantity' => 1,
                    'slot_index' => $this->findEmptySlot($character, false),
                ]);
            }
        }

        // 扣除金币
        $character->gold -= $totalPrice;
        $character->save();

        return $this->success([
            'gold' => $character->gold,
            'total_price' => $totalPrice,
            'quantity' => $quantity,
            'item_name' => $definition->name,
        ], "购买成功，消耗 {$totalPrice} 金币");
    }

    /**
     * 出售背包物品（商店出售接口，和 InventoryController::sell 类似但返回更多信息）
     */
    public function sell(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_items,id',
            'quantity' => 'sometimes|integer|min:1',
        ]);

        $character = $this->getCharacter($request);
        $quantity = $validated['quantity'] ?? 1;

        $item = GameItem::query()
            ->where('id', $validated['item_id'])
            ->where('character_id', $character->id)
            ->with('definition')
            ->first();

        if (! $item) {
            return $this->error('物品不存在或不属于你');
        }

        // 检查是否在背包中
        if ($item->is_in_storage) {
            return $this->error('请先将物品从仓库移到背包');
        }

        // 检查装备中的物品
        $equipped = $character->equipment()->where('item_id', $item->id)->exists();
        if ($equipped) {
            return $this->error('请先卸下装备');
        }

        if ($item->quantity < $quantity) {
            return $this->error('物品数量不足');
        }

        // 计算售价
        $sellPrice = $this->calculateItemSellPrice($item) * $quantity;

        // 更新金币
        $character->gold += $sellPrice;
        $character->save();

        // 减少或删除物品
        if ($item->quantity > $quantity) {
            $item->quantity -= $quantity;
            $item->save();
        } else {
            $item->delete();
        }

        return $this->success([
            'gold' => $character->gold,
            'sell_price' => $sellPrice,
            'quantity' => $quantity,
            'item_name' => $item->definition->name,
        ], "出售成功，获得 {$sellPrice} 金币");
    }

    /**
     * 获取角色
     */
    private function getCharacter(Request $request): GameCharacter
    {
        $characterId = $request->query('character_id') ?: $request->input('character_id');

        $query = GameCharacter::query()
            ->where('user_id', $request->user()->id);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        return $query->firstOrFail();
    }

    /**
     * 计算购买价格
     */
    private function calculateBuyPrice(GameItemDefinition $item): int
    {
        // 基础价格来自 base_stats 中的 price 字段，如果没有则根据属性计算
        $basePrice = $item->base_stats['price'] ?? 0;

        if ($basePrice > 0) {
            return $basePrice;
        }

        // 根据物品类型和属性计算价格
        $stats = $item->base_stats ?? [];
        $levelMultiplier = 1 + ($item->required_level * 0.5);

        // 基础价格根据类型
        $typeBasePrice = match ($item->type) {
            'potion' => 10,
            'weapon' => 50,
            'helmet' => 30,
            'armor' => 40,
            'gloves' => 20,
            'boots' => 20,
            'belt' => 25,
            'ring' => 35,
            'amulet' => 45,
            default => 20,
        };

        // 计算属性加成价格
        $statsPrice = 0;
        foreach ($stats as $stat => $value) {
            $statValue = match ($stat) {
                'attack' => $value * 5,
                'defense' => $value * 4,
                'max_hp' => $value * 0.5,
                'max_mana' => $value * 0.6,
                'crit_rate' => $value * 100,
                'crit_damage' => $value * 80,
                'strength', 'dexterity', 'vitality', 'energy' => $value * 10,
                'all_stats' => $value * 40,
                default => $value * 2,
            };
            $statsPrice += $statValue;
        }

        return (int) (($typeBasePrice + $statsPrice) * $levelMultiplier);
    }

    /**
     * 计算出售价格（给玩家看的预估售价）
     */
    private function calculateSellPrice(GameItemDefinition $item): int
    {
        return (int) ($this->calculateBuyPrice($item) * 0.5);
    }

    /**
     * 计算实际物品出售价格（考虑品质）
     */
    private function calculateItemSellPrice(GameItem $item): int
    {
        $buyPrice = $this->calculateBuyPrice($item->definition);
        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$item->quality] ?? 1.0;

        return (int) ($buyPrice * $qualityMultiplier * 0.5);
    }

    /**
     * 查找空槽位
     */
    private function findEmptySlot(GameCharacter $character, bool $inStorage): ?int
    {
        $maxSize = $inStorage ? InventoryController::STORAGE_SIZE : InventoryController::INVENTORY_SIZE;

        $usedSlots = $character->items()
            ->where('is_in_storage', $inStorage)
            ->whereNotNull('slot_index')
            ->pluck('slot_index')
            ->toArray();

        for ($i = 0; $i < $maxSize; $i++) {
            if (! in_array($i, $usedSlots)) {
                return $i;
            }
        }

        return null;
    }
}
