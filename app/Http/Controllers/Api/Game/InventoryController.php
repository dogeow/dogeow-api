<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public const INVENTORY_SIZE = 100;

    public const STORAGE_SIZE = 100;

    /**
     * 获取背包物品
     */
    public function index(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $inventory = $character->items()
            ->where('is_in_storage', false)
            ->with('definition')
            ->orderBy('slot_index')
            ->get();

        $storage = $character->items()
            ->where('is_in_storage', true)
            ->with('definition')
            ->orderBy('slot_index')
            ->get();

        $equipment = $character->equipment()
            ->with('item.definition')
            ->get()
            ->keyBy('slot');

        return $this->success([
            'inventory' => $inventory,
            'storage' => $storage,
            'equipment' => $equipment,
            'inventory_size' => self::INVENTORY_SIZE,
            'storage_size' => self::STORAGE_SIZE,
        ]);
    }

    /**
     * 装备物品
     */
    public function equip(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_items,id',
        ]);

        $character = $this->getCharacter($request);

        $item = GameItem::query()
            ->where('id', $validated['item_id'])
            ->where('character_id', $character->id)
            ->where('is_in_storage', false)
            ->with('definition')
            ->first();

        if (! $item) {
            return $this->error('物品不存在或不属于你');
        }

        // 检查是否可以装备
        $canEquip = $item->canEquip($character);
        if (! $canEquip['can_equip']) {
            return $this->error($canEquip['reason']);
        }

        // 确定装备槽位
        $slot = $item->definition->getEquipmentSlot();
        if (! $slot) {
            return $this->error('该物品无法装备');
        }

        // 如果是戒指，检查两个戒指槽位
        if ($item->definition->type === 'ring') {
            $ring1 = $character->equipment()->where('slot', 'ring1')->first();
            $ring2 = $character->equipment()->where('slot', 'ring2')->first();

            if ($ring1 && ! $ring1->item_id) {
                $slot = 'ring1';
            } elseif ($ring2 && ! $ring2->item_id) {
                $slot = 'ring2';
            } else {
                // 两个槽位都有装备，替换第一个
                $slot = 'ring1';
            }
        }

        // 获取装备槽
        $equipmentSlot = $character->equipment()->where('slot', $slot)->first();

        if (! $equipmentSlot) {
            $equipmentSlot = $character->equipment()->create(['slot' => $slot]);
        }

        // 如果槽位已有装备，先卸下
        $oldItem = null;
        if ($equipmentSlot->item_id) {
            $oldItem = GameItem::find($equipmentSlot->item_id);
            if ($oldItem) {
                $oldItem->slot_index = $this->findEmptySlot($character, false);
                $oldItem->save();
            }
        }

        // 装备新物品
        $equipmentSlot->item_id = $item->id;
        $equipmentSlot->save();

        // 从背包移除
        $item->slot_index = null;
        $item->save();

        return $this->success([
            'equipped_item' => $item->fresh()->load('definition'),
            'equipped_slot' => $slot,
            'unequipped_item' => $oldItem ? $oldItem->load('definition') : null,
            'combat_stats' => $character->fresh()->getCombatStats(),
            'stats_breakdown' => $character->fresh()->getCombatStatsBreakdown(),
        ], '装备成功');
    }

    /**
     * 卸下装备
     */
    public function unequip(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slot' => 'required|in:weapon,helmet,armor,gloves,boots,belt,ring1,ring2,amulet',
        ]);

        $character = $this->getCharacter($request);

        $equipmentSlot = $character->equipment()->where('slot', $validated['slot'])->first();

        if (! $equipmentSlot || ! $equipmentSlot->item_id) {
            return $this->error('该槽位没有装备');
        }

        // 检查背包空间
        $emptySlot = $this->findEmptySlot($character, false);
        if ($emptySlot === null) {
            return $this->error('背包已满');
        }

        $item = GameItem::with('definition')->find($equipmentSlot->item_id);

        // 卸下装备到背包
        if ($item) {
            $item->slot_index = $emptySlot;
            $item->save();
        }

        $equipmentSlot->item_id = null;
        $equipmentSlot->save();

        return $this->success([
            'item' => $item,
            'combat_stats' => $character->fresh()->getCombatStats(),
            'stats_breakdown' => $character->fresh()->getCombatStatsBreakdown(),
        ], '卸下装备成功');
    }

    /**
     * 出售物品
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

        // 检查装备中的物品
        $equipped = $character->equipment()->where('item_id', $item->id)->exists();
        if ($equipped) {
            return $this->error('请先卸下装备');
        }

        if ($item->quantity < $quantity) {
            return $this->error('物品数量不足');
        }

        // 计算售价（铜币，与商店一致 1银=100铜）
        $basePrice = $item->definition->base_stats['price'] ?? 10;
        $qualityMultiplier = GameItem::QUALITY_MULTIPLIERS[$item->quality] ?? 1.0;
        $sellPrice = (int) ($basePrice * $qualityMultiplier * $quantity * 0.5 * 100);

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
        ], '出售成功');
    }

    /**
     * 移动物品（背包 <-> 仓库）
     */
    public function move(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_items,id',
            'to_storage' => 'required|boolean',
            'slot_index' => 'sometimes|integer|min:0',
        ]);

        $character = $this->getCharacter($request);

        $item = GameItem::query()
            ->where('id', $validated['item_id'])
            ->where('character_id', $character->id)
            ->first();

        if (! $item) {
            return $this->error('物品不存在或不属于你');
        }

        $toStorage = $validated['to_storage'];

        // 检查目标空间
        if ($toStorage) {
            $storageCount = $character->items()->where('is_in_storage', true)->count();
            if ($storageCount >= self::STORAGE_SIZE) {
                return $this->error('仓库已满');
            }
        } else {
            $inventoryCount = $character->items()->where('is_in_storage', false)->count();
            if ($inventoryCount >= self::INVENTORY_SIZE) {
                return $this->error('背包已满');
            }
        }

        $item->is_in_storage = $toStorage;
        $item->slot_index = $validated['slot_index'] ?? $this->findEmptySlot($character, $toStorage);
        $item->save();

        return $this->success(['item' => $item], '移动成功');
    }

    /**
     * 使用药品
     */
    public function usePotion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character_id' => 'required|integer|exists:game_characters,id',
            'item_id' => 'required|integer|exists:game_items,id',
        ]);

        $character = $this->getCharacter($request);

        $item = GameItem::query()
            ->where('id', $validated['item_id'])
            ->where('character_id', $character->id)
            ->where('is_in_storage', false)
            ->with('definition')
            ->first();

        if (! $item) {
            return $this->error('物品不存在或不属于你');
        }

        // 检查是否是药品
        if ($item->definition->type !== 'potion') {
            return $this->error('该物品不是药品');
        }

        // 检查装备中的物品
        $equipped = $character->equipment()->where('item_id', $item->id)->exists();
        if ($equipped) {
            return $this->error('请先卸下装备');
        }

        // 获取药品效果（支持多种恢复方式）
        $baseStats = $item->definition->base_stats ?? [];
        $hpRestore = $baseStats['max_hp'] ?? $baseStats['restore_amount'] ?? 0;
        $manaRestore = $baseStats['max_mana'] ?? 0;

        $definitionName = $item->definition->name;
        $itemId = (int) $item->getRawOriginal('id');
        $quantity = (int) $item->quantity;

        $affected = 0;
        DB::transaction(function () use ($character, $itemId, $quantity, $hpRestore, $manaRestore, &$affected) {
            // 恢复HP/Mana
            if ($hpRestore > 0) {
                $character->restoreHp($hpRestore);
            }
            if ($manaRestore > 0) {
                $character->restoreMana($manaRestore);
            }

            // 直接对 game_items 表扣减数量或删除（按 id + character_id 双重条件，避免误删）
            $query = DB::table('game_items')
                ->where('id', $itemId)
                ->where('character_id', $character->id);
            if ($quantity > 1) {
                $affected = $query->decrement('quantity', 1);
            } else {
                $affected = $query->delete();
            }
        });

        if ($affected === 0) {
            return $this->error('消耗药品失败，请重试');
        }

        $character->refresh();

        // 构建恢复消息
        $restoreText = [];
        if ($hpRestore > 0) {
            $restoreText[] = "{$hpRestore} 点生命值";
        }
        if ($manaRestore > 0) {
            $restoreText[] = "{$manaRestore} 点法力值";
        }
        $restoreMessage = implode('和', $restoreText);

        return $this->success([
            'character' => $character,
            'combat_stats' => $character->getCombatStats(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
            'message' => "使用{$definitionName}成功，恢复了 {$restoreMessage}",
        ], '使用药品成功');
    }

    /**
     * 整理背包
     */
    public function sort(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $items = $character->items()
            ->where('is_in_storage', false)
            ->orderBy('definition_id')
            ->orderBy('quality')
            ->orderByDesc('quantity')
            ->get();

        $slotIndex = 0;
        foreach ($items as $item) {
            $item->slot_index = $slotIndex++;
            $item->save();
        }

        return $this->success(['inventory' => $items->fresh()], '整理完成');
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
     * 查找空槽位
     */
    private function findEmptySlot(GameCharacter $character, bool $inStorage): ?int
    {
        $maxSize = $inStorage ? self::STORAGE_SIZE : self::INVENTORY_SIZE;

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
