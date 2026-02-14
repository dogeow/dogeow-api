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
     * 获取商店物品列表（随机物品，随机属性）
     */
    public function index(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        // 药品按 sub_type（hp/mp）各只显示一瓶：取玩家可用的最高等级那种
        $potionDefinitions = GameItemDefinition::query()
            ->where('is_active', true)
            ->where('type', 'potion')
            ->where('required_level', '<=', $character->level)
            ->orderBy('sub_type')
            ->orderByDesc('required_level')
            ->get();

        $fixedPotions = $potionDefinitions->unique('sub_type')->values();

        $fixedPotionItems = $fixedPotions->map(function ($definition) {
            $randomStats = $this->generateRandomStats($definition);
            $buyPrice = $this->calculateBuyPrice($definition, $randomStats);

            return [
                'id' => $definition->id,
                'name' => $definition->name,
                'type' => $definition->type,
                'sub_type' => $definition->sub_type,
                'base_stats' => $randomStats,
                'required_level' => $definition->required_level,
                'required_strength' => $definition->required_strength,
                'required_dexterity' => $definition->required_dexterity,
                'required_energy' => $definition->required_energy,
                'icon' => $definition->icon,
                'description' => $definition->description,
                'buy_price' => $buyPrice,
                'sell_price' => (int) floor($buyPrice * 0.3),
            ];
        });

        // 获取所有非药品的物品定义
        $equipmentDefinitions = GameItemDefinition::query()
            ->where('is_active', true)
            ->where('type', '!=', 'potion')
            ->orderBy('type')
            ->orderBy('required_level')
            ->get();

        // 随机选择 6-12 件装备
        $shopSize = rand(6, 12);
        $selectedEquipments = $equipmentDefinitions->shuffle()->take($shopSize);

        // 为每个装备生成随机属性和价格
        $randomEquipmentItems = $selectedEquipments->map(function ($definition) {
            $randomStats = $this->generateRandomStats($definition);

            return [
                'id' => $definition->id,
                'name' => $definition->name,
                'type' => $definition->type,
                'sub_type' => $definition->sub_type,
                'base_stats' => $randomStats,
                'required_level' => $definition->required_level,
                'required_strength' => $definition->required_strength,
                'required_dexterity' => $definition->required_dexterity,
                'required_energy' => $definition->required_energy,
                'icon' => $definition->icon,
                'description' => $definition->description,
                'buy_price' => $this->calculateBuyPrice($definition, $randomStats),
            ];
        });

        // 合并固定药品和随机装备
        $shopItems = $fixedPotionItems->concat($randomEquipmentItems);

        return $this->success([
            'items' => $shopItems,
            'player_copper' => $character->copper,
        ]);
    }

    /**
     * 生成随机属性
     */
    private function generateRandomStats(GameItemDefinition $definition): array
    {
        $stats = [];
        $type = $definition->type;

        // 根据物品类型生成不同的随机属性
        switch ($type) {
            case 'weapon':
                $stats['attack'] = rand(5, 15) + $definition->required_level * 2;
                if (rand(1, 100) <= 30) {
                    $stats['crit_rate'] = rand(1, 10) / 100;
                }
                if (rand(1, 100) <= 20) {
                    $stats['crit_damage'] = rand(20, 50);
                }
                break;

            case 'helmet':
            case 'armor':
                $stats['defense'] = rand(3, 10) + $definition->required_level;
                $stats['max_hp'] = rand(10, 30) + $definition->required_level * 5;
                if (rand(1, 100) <= 25) {
                    $stats['crit_rate'] = rand(1, 5) / 100;
                }
                break;

            case 'gloves':
                $stats['attack'] = rand(2, 6) + $definition->required_level;
                $stats['crit_rate'] = rand(2, 8) / 100;
                break;

            case 'boots':
                $stats['defense'] = rand(1, 5) + $definition->required_level;
                $stats['max_hp'] = rand(5, 20) + $definition->required_level * 3;
                if (rand(1, 100) <= 30) {
                    $stats['dexterity'] = rand(1, 3);
                }
                break;

            case 'belt':
                $stats['max_hp'] = rand(15, 40) + $definition->required_level * 4;
                $stats['max_mana'] = rand(10, 30) + $definition->required_level * 3;
                break;

            case 'ring':
                $ringStats = ['attack', 'defense', 'max_hp', 'max_mana', 'crit_rate', 'strength', 'dexterity', 'energy'];
                $selectedStat = $ringStats[array_rand($ringStats)];
                if ($selectedStat === 'crit_rate') {
                    $stats[$selectedStat] = rand(1, 8) / 100;
                } else {
                    $stats[$selectedStat] = rand(3, 12) + $definition->required_level * 2;
                }
                // 戒指可能有两条属性
                if (rand(1, 100) <= 40) {
                    $secondStat = $ringStats[array_rand($ringStats)];
                    if ($secondStat === 'crit_rate') {
                        $stats[$secondStat] = rand(1, 5) / 100;
                    } else {
                        $stats[$secondStat] = rand(2, 8) + $definition->required_level;
                    }
                }
                break;

            case 'amulet':
                $stats['max_hp'] = rand(20, 50) + $definition->required_level * 5;
                $stats['max_mana'] = rand(15, 40) + $definition->required_level * 4;
                if (rand(1, 100) <= 30) {
                    $stats['defense'] = rand(5, 15);
                }
                break;

            case 'potion':
                $potionTypes = ['hp', 'mp'];
                $potionType = $potionTypes[array_rand($potionTypes)];
                $restoreAmount = rand(30, 100) + $definition->required_level * 10;
                $stats[$potionType === 'hp' ? 'max_hp' : 'max_mana'] = $restoreAmount;
                $stats['restore'] = $restoreAmount;
                break;
        }

        return $stats;
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

        // 生成随机属性（与商店显示一致）
        $randomStats = $this->generateRandomStats($definition);

        // 计算总价
        $totalPrice = $this->calculateBuyPrice($definition, $randomStats) * $quantity;

        // 检查铜币是否足够
        if ($character->copper < $totalPrice) {
            return $this->error('货币不足');
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
                    'stats' => $randomStats,
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
                    'stats' => $randomStats,
                    'affixes' => [],
                    'is_in_storage' => false,
                    'quantity' => 1,
                    'slot_index' => $this->findEmptySlot($character, false),
                ]);
            }
        }

        // 扣除铜币
        $character->copper -= $totalPrice;
        $character->save();

        return $this->success([
            'copper' => $character->copper,
            'total_price' => $totalPrice,
            'quantity' => $quantity,
            'item_name' => $definition->name,
        ], '购买成功');
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

        // 更新铜币
        $character->copper += $sellPrice;
        $character->save();

        // 减少或删除物品
        if ($item->quantity > $quantity) {
            $item->quantity -= $quantity;
            $item->save();
        } else {
            $item->delete();
        }

        return $this->success([
            'copper' => $character->copper,
            'sell_price' => $sellPrice,
            'quantity' => $quantity,
            'item_name' => $item->definition->name,
        ], '出售成功');
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
    private function calculateBuyPrice(GameItemDefinition $item, array $stats = []): int
    {
        // 基础价格来自 base_stats 中的 price 字段，如果没有则根据属性计算
        $basePrice = $item->base_stats['price'] ?? 0;

        if ($basePrice > 0) {
            return $basePrice;
        }

        // 根据物品类型和属性计算价格
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
                'max_hp', 'max_mana' => $value * 0.5,
                'crit_rate' => $value * 100,
                'crit_damage' => $value * 2,
                'strength', 'dexterity', 'vitality', 'energy' => $value * 10,
                'all_stats' => $value * 40,
                default => $value * 2,
            };
            $statsPrice += $statValue;
        }

        return (int) (($typeBasePrice + $statsPrice) * $levelMultiplier * 100); // 价格单位为铜币（1银=100铜）
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
