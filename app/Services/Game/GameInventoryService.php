<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use Illuminate\Support\Facades\DB;

class GameInventoryService
{
    public const INVENTORY_SIZE = 100;

    public const STORAGE_SIZE = 50;

    /**
     * 获取背包物品
     */
    public function getInventory(GameCharacter $character): array
    {
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

        return [
            'inventory' => $inventory,
            'storage' => $storage,
            'equipment' => $equipment,
            'inventory_size' => self::INVENTORY_SIZE,
            'storage_size' => self::STORAGE_SIZE,
        ];
    }

    /**
     * 装备物品
     */
    public function equipItem(GameCharacter $character, int $itemId): array
    {
        $item = GameItem::query()
            ->where('id', $itemId)
            ->where('character_id', $character->id)
            ->where('is_in_storage', false)
            ->with('definition')
            ->first();

        if (! $item) {
            throw new \InvalidArgumentException('物品不存在或不属于你');
        }

        // 检查是否可以装备
        $canEquip = $item->canEquip($character);
        if (! $canEquip['can_equip']) {
            throw new \InvalidArgumentException($canEquip['reason']);
        }

        // 确定装备槽位
        $slot = $item->definition->getEquipmentSlot();
        if (! $slot) {
            throw new \InvalidArgumentException('该物品无法装备');
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
                $slot = 'ring1';
            }
        }

        return DB::transaction(function () use ($character, $item, $slot) {
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

            $character->refresh();

            return [
                'equipped_item' => $item->fresh()->load('definition'),
                'equipped_slot' => $slot,
                'unequipped_item' => $oldItem ? $oldItem->load('definition') : null,
                'combat_stats' => $character->getCombatStats(),
                'stats_breakdown' => $character->getCombatStatsBreakdown(),
            ];
        });
    }

    /**
     * 卸下装备
     */
    public function unequipItem(GameCharacter $character, string $slot): array
    {
        $equipmentSlot = $character->equipment()->where('slot', $slot)->first();

        if (! $equipmentSlot || ! $equipmentSlot->item_id) {
            throw new \InvalidArgumentException('该槽位没有装备');
        }

        // 检查背包空间
        $emptySlot = $this->findEmptySlot($character, false);
        if ($emptySlot === null) {
            throw new \InvalidArgumentException('背包已满');
        }

        return DB::transaction(function () use ($character, $equipmentSlot, $emptySlot) {
            $item = GameItem::with('definition')->find($equipmentSlot->item_id);

            // 卸下装备到背包
            if ($item) {
                $item->slot_index = $emptySlot;
                $item->save();
            }

            $equipmentSlot->item_id = null;
            $equipmentSlot->save();

            $character->refresh();

            return [
                'item' => $item,
                'combat_stats' => $character->getCombatStats(),
                'stats_breakdown' => $character->getCombatStatsBreakdown(),
            ];
        });
    }

    /**
     * 出售物品
     */
    public function sellItem(GameCharacter $character, int $itemId, int $quantity = 1): array
    {
        $item = GameItem::query()
            ->where('id', $itemId)
            ->where('character_id', $character->id)
            ->with('definition')
            ->first();

        if (! $item) {
            throw new \InvalidArgumentException('物品不存在或不属于你');
        }

        // 检查装备中的物品
        $equipped = $character->equipment()->where('item_id', $item->id)->exists();
        if ($equipped) {
            throw new \InvalidArgumentException('请先卸下装备');
        }

        if ($item->quantity < $quantity) {
            throw new \InvalidArgumentException('物品数量不足');
        }

        // 使用基于属性的价格计算公式
        $sellPrice = $item->calculateSellPrice() * $quantity;

        return DB::transaction(function () use ($character, $item, $quantity, $sellPrice) {
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

            return [
                'copper' => $character->copper,
                'sell_price' => $sellPrice,
            ];
        });
    }

    /**
     * 移动物品
     */
    public function moveItem(GameCharacter $character, int $itemId, bool $toStorage, ?int $slotIndex = null): array
    {
        $item = GameItem::query()
            ->where('id', $itemId)
            ->where('character_id', $character->id)
            ->first();

        if (! $item) {
            throw new \InvalidArgumentException('物品不存在或不属于你');
        }

        // 检查目标空间
        if ($toStorage) {
            $storageCount = $character->items()->where('is_in_storage', true)->count();
            if ($storageCount >= self::STORAGE_SIZE) {
                throw new \InvalidArgumentException('仓库已满');
            }
        } else {
            $inventoryCount = $character->items()->where('is_in_storage', false)->count();
            if ($inventoryCount >= self::INVENTORY_SIZE) {
                throw new \InvalidArgumentException('背包已满');
            }
        }

        $item->is_in_storage = $toStorage;
        $item->slot_index = $slotIndex ?? $this->findEmptySlot($character, $toStorage);
        $item->save();

        return ['item' => $item];
    }

    /**
     * 使用药品
     */
    public function usePotion(GameCharacter $character, int $itemId): array
    {
        $item = GameItem::query()
            ->where('id', $itemId)
            ->where('character_id', $character->id)
            ->where('is_in_storage', false)
            ->with('definition')
            ->first();

        if (! $item) {
            throw new \InvalidArgumentException('物品不存在或不属于你');
        }

        // 检查是否是药品
        if ($item->definition->type !== 'potion') {
            throw new \InvalidArgumentException('该物品不是药品');
        }

        // 检查装备中的物品
        $equipped = $character->equipment()->where('item_id', $item->id)->exists();
        if ($equipped) {
            throw new \InvalidArgumentException('请先卸下装备');
        }

        // 获取药品效果
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

            // 扣减数量或删除
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
            throw new \RuntimeException('消耗药品失败，请重试');
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

        return [
            'character' => $character,
            'combat_stats' => $character->getCombatStats(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
            'message' => "使用{$definitionName}成功，恢复了 {$restoreMessage}",
        ];
    }

    /**
     * 整理背包
     */
    public function sortInventory(GameCharacter $character): array
    {
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

        return ['inventory' => $items->fresh()];
    }

    /**
     * 查找空槽位
     */
    public function findEmptySlot(GameCharacter $character, bool $inStorage): ?int
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
