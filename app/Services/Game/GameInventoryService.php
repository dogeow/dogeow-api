<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * 背包服务类
 *
 * 负责背包相关的业务逻辑，包括物品装备、卸下、出售、移动等
 */
class GameInventoryService
{
    /** 背包默认大小 */
    public const INVENTORY_SIZE = 100;

    /** 仓库默认大小 */
    public const STORAGE_SIZE = 50;

    /** 缓存键前缀 */
    private const CACHE_PREFIX = 'game_inventory:';

    /** 缓存有效期（秒） */
    private const CACHE_TTL = 60;

    /**
     * 获取背包物品
     *
     * @param GameCharacter $character 角色实例
     * @return array 背包数据
     */
    public function getInventory(GameCharacter $character): array
    {
        // 背包：不在仓库且未装备
        $inventory = $character->items()
            ->where('is_in_storage', false)
            ->where('is_equipped', false)
            ->with(['definition', 'gems.gemDefinition'])
            ->orderBy('slot_index')
            ->get();

        // 仓库：未装备
        $storage = $character->items()
            ->where('is_in_storage', true)
            ->where('is_equipped', false)
            ->with(['definition', 'gems.gemDefinition'])
            ->orderBy('slot_index')
            ->get();

        $equipment = $character->equipment()
            ->with(['item.definition', 'item.gems.gemDefinition'])
            ->get()
            ->keyBy('slot');

        $this->ensureItemsSellPrice($inventory);
        $this->ensureItemsSellPrice($storage);
        foreach ($equipment as $eq) {
            if ($eq->item) {
                $this->ensureItemsSellPrice(collect([$eq->item]));
            }
        }

        return [
            'inventory' => $inventory,
            'storage' => $storage,
            'equipment' => $equipment,
            'inventory_size' => self::INVENTORY_SIZE,
            'storage_size' => self::STORAGE_SIZE,
        ];
    }

    /**
     * 确保物品列表中的 sell_price 已计算（若为 0 或未设置则按属性计算）
     *
     * @param  \Illuminate\Support\Collection<int, GameItem>  $items
     */
    private function ensureItemsSellPrice(\Illuminate\Support\Collection $items): void
    {
        foreach ($items as $item) {
            if (! $item instanceof GameItem) {
                continue;
            }
            if (! isset($item->sell_price) || $item->sell_price === 0) {
                $item->sell_price = $item->calculateSellPrice();
                $item->saveQuietly();
            }
        }
    }

    /**
     * 获取背包数据（用于 WebSocket 广播）
     *
     * @param GameCharacter $character 角色实例
     * @return array 背包数组数据
     */
    public function getInventoryForBroadcast(GameCharacter $character): array
    {
        $result = $this->getInventory($character);
        $equipmentArray = [];

        foreach ($result['equipment'] as $slot => $eq) {
            $equipmentArray[$slot] = $eq->item ? $eq->item->toArray() : null;
        }

        return [
            'inventory' => $result['inventory']->toArray(),
            'storage' => $result['storage']->toArray(),
            'equipment' => $equipmentArray,
            'inventory_size' => $result['inventory_size'],
            'storage_size' => $result['storage_size'],
        ];
    }

    /**
     * 装备物品
     *
     * @param GameCharacter $character 角色实例
     * @param int $itemId 物品ID
     * @return array 装备结果
     * @throws \InvalidArgumentException 物品不存在或无法装备
     */
    public function equipItem(GameCharacter $character, int $itemId): array
    {
        $item = $this->findItem($character, $itemId, false);

        // 检查是否可以装备
        $canEquip = $item->canEquip($character);
        if (! $canEquip['can_equip']) {
            throw new \InvalidArgumentException($canEquip['reason']);
        }

        // 确定装备槽位
        $slot = $this->determineEquipmentSlot($character, $item);

        return DB::transaction(function () use ($character, $item, $slot) {
            $equipmentSlot = $this->getOrCreateEquipmentSlot($character, $slot);

            // 如果槽位已有装备，先卸下
            $oldItem = $this->handleUnequipIfNeeded($character, $equipmentSlot);

            // 装备新物品
            $equipmentSlot->item_id = $item->id;
            $equipmentSlot->save();

            // 标记为已装备
            $item->is_equipped = true;
            $item->slot_index = null;
            $item->save();

            $character->refresh();

            // 清除缓存
            $this->clearInventoryCache($character->id);

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
     *
     * @param GameCharacter $character 角色实例
     * @param string $slot 装备槽位
     * @return array 卸下结果
     * @throws \InvalidArgumentException 槽位没有装备或背包已满
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
                $item->is_equipped = false;
                $item->slot_index = $emptySlot;
                $item->save();
            }

            $equipmentSlot->item_id = null;
            $equipmentSlot->save();

            $character->refresh();

            // 清除缓存
            $this->clearInventoryCache($character->id);

            return [
                'item' => $item,
                'combat_stats' => $character->getCombatStats(),
                'stats_breakdown' => $character->getCombatStatsBreakdown(),
            ];
        });
    }

    /**
     * 出售物品
     *
     * @param GameCharacter $character 角色实例
     * @param int $itemId 物品ID
     * @param int $quantity 数量
     * @return array 出售结果
     * @throws \InvalidArgumentException 物品不存在或数量不足
     */
    public function sellItem(GameCharacter $character, int $itemId, int $quantity = 1): array
    {
        $item = $this->findItem($character, $itemId);

        // 检查装备中的物品
        if ($this->isItemEquipped($character, $itemId)) {
            throw new \InvalidArgumentException('请先卸下装备');
        }

        if ($item->quantity < $quantity) {
            throw new \InvalidArgumentException('物品数量不足');
        }

        // 计算售价
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

            // 清除缓存
            $this->clearInventoryCache($character->id);

            return [
                'copper' => $character->copper,
                'sell_price' => $sellPrice,
            ];
        });
    }

    /**
     * 移动物品
     *
     * @param GameCharacter $character 角色实例
     * @param int $itemId 物品ID
     * @param bool $toStorage 是否移动到仓库
     * @param int|null $slotIndex 指定槽位（可选）
     * @return array 移动结果
     * @throws \InvalidArgumentException 目标位置已满
     */
    public function moveItem(GameCharacter $character, int $itemId, bool $toStorage, ?int $slotIndex = null): array
    {
        $item = $this->findItem($character, $itemId);

        // 检查目标空间
        $this->checkStorageSpace($character, $toStorage);

        $item->is_in_storage = $toStorage;
        $item->slot_index = $slotIndex ?? $this->findEmptySlot($character, $toStorage);
        $item->save();

        // 清除缓存
        $this->clearInventoryCache($character->id);

        return ['item' => $item];
    }

    /**
     * 使用药品
     *
     * @param GameCharacter $character 角色实例
     * @param int $itemId 物品ID
     * @return array 使用结果
     * @throws \InvalidArgumentException 物品不存在或不是药品
     */
    public function usePotion(GameCharacter $character, int $itemId): array
    {
        $item = $this->findItem($character, $itemId, false);

        // 检查是否是药品
        if ($item->definition->type !== 'potion') {
            throw new \InvalidArgumentException('该物品不是药品');
        }

        // 检查是否已装备
        if ($this->isItemEquipped($character, $itemId)) {
            throw new \InvalidArgumentException('请先卸下装备');
        }

        // 获取药品效果
        $effects = $this->getPotionEffects($item);

        $definitionName = $item->definition->name;
        $itemDbId = (int) $item->getRawOriginal('id');
        $quantity = (int) $item->quantity;

        $affected = 0;
        DB::transaction(function () use ($character, $itemDbId, $quantity, $effects, &$affected) {
            // 恢复HP/Mana
            if ($effects['hp'] > 0) {
                $character->restoreHp($effects['hp']);
            }
            if ($effects['mana'] > 0) {
                $character->restoreMana($effects['mana']);
            }

            // 扣减数量或删除
            $query = DB::table('game_items')
                ->where('id', $itemDbId)
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
        $restoreMessage = $this->formatRestoreMessage($effects);

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
     *
     * @param GameCharacter $character 角色实例
     * @param string $sortBy 排序方式: quality, price, default
     * @return array 整理结果
     */
    public function sortInventory(GameCharacter $character, string $sortBy = 'default'): array
    {
        $query = $character->items()
            ->where('is_in_storage', false)
            ->with('definition');

        $items = $this->sortItems($query, $sortBy);

        $slotIndex = 0;
        foreach ($items as $item) {
            $item->slot_index = $slotIndex++;
            $item->save();
        }

        // 清除缓存
        $this->clearInventoryCache($character->id);

        return ['inventory' => $items->fresh()];
    }

    /**
     * 批量出售指定品质的物品
     *
     * @param GameCharacter $character 角色实例
     * @param string $quality 品质
     * @return array 出售结果
     */
    public function sellItemsByQuality(GameCharacter $character, string $quality): array
    {
        $items = $this->getSellableItemsByQuality($character, $quality);

        if ($items->isEmpty()) {
            return [
                'count' => 0,
                'total_price' => 0,
                'copper' => $character->copper,
            ];
        }

        return DB::transaction(function () use ($character, $items) {
            $totalPrice = 0;
            $count = 0;

            foreach ($items as $item) {
                // 检查是否已装备
                if ($this->isItemEquipped($character, $item->id)) {
                    continue;
                }

                $price = $item->calculateSellPrice() * $item->quantity;
                $totalPrice += $price;
                $count++;

                $item->delete();
            }

            $character->copper += $totalPrice;
            $character->save();

            // 清除缓存
            $this->clearInventoryCache($character->id);

            return [
                'count' => $count,
                'total_price' => $totalPrice,
                'copper' => $character->copper,
            ];
        });
    }

    /**
     * 查找空槽位
     *
     * @param GameCharacter $character 角色实例
     * @param bool $inStorage 是否在仓库中
     * @return int|null 空槽位索引
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

    // ==================== 私有辅助方法 ====================

    /**
     * 查找物品
     */
    private function findItem(GameCharacter $character, int $itemId, bool $checkStorage = true): GameItem
    {
        $query = GameItem::query()
            ->where('id', $itemId)
            ->where('character_id', $character->id)
            ->where('is_equipped', false); // 排除已装备的物品

        if ($checkStorage) {
            $query->where('is_in_storage', false);
        }

        $item = $query->with('definition')->first();

        if (! $item) {
            throw new \InvalidArgumentException('物品不存在或不属于你');
        }

        return $item;
    }

    /**
     * 确定装备槽位
     */
    private function determineEquipmentSlot(GameCharacter $character, GameItem $item): string
    {
        $slot = $item->definition->getEquipmentSlot();

        if (! $slot) {
            throw new \InvalidArgumentException('该物品无法装备');
        }

        // 如果是戒指，检查两个戒指槽位
        if ($item->definition->type === 'ring') {
            $slot = $this->findAvailableRingSlot($character);
        }

        return $slot;
    }

    /**
     * 查找可用的戒指槽位
     */
    private function findAvailableRingSlot(GameCharacter $character): string
    {
        $ring = $character->equipment()->where('slot', 'ring')->first();

        if ($ring && ! $ring->item_id) {
            return 'ring';
        }

        return 'ring';
    }

    /**
     * 获取或创建装备槽位
     */
    private function getOrCreateEquipmentSlot(GameCharacter $character, string $slot)
    {
        $equipmentSlot = $character->equipment()->where('slot', $slot)->first();

        if (! $equipmentSlot) {
            $equipmentSlot = $character->equipment()->create(['slot' => $slot]);
        }

        return $equipmentSlot;
    }

    /**
     * 如果需要则卸下装备
     */
    private function handleUnequipIfNeeded(GameCharacter $character, $equipmentSlot): ?GameItem
    {
        $oldItem = null;

        if ($equipmentSlot->item_id) {
            $oldItem = GameItem::find($equipmentSlot->item_id);
            if ($oldItem) {
                $oldItem->slot_index = $this->findEmptySlot($character, false);
                $oldItem->save();
            }
        }

        return $oldItem;
    }

    /**
     * 检查物品是否已装备
     */
    private function isItemEquipped(GameCharacter $character, int $itemId): bool
    {
        return $character->equipment()->where('item_id', $itemId)->exists();
    }

    /**
     * 检查存储空间
     */
    private function checkStorageSpace(GameCharacter $character, bool $toStorage): void
    {
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
    }

    /**
     * 获取药品效果
     */
    private function getPotionEffects(GameItem $item): array
    {
        $baseStats = $item->definition->base_stats ?? [];

        return [
            'hp' => $baseStats['max_hp'] ?? $baseStats['restore_amount'] ?? 0,
            'mana' => $baseStats['max_mana'] ?? 0,
        ];
    }

    /**
     * 格式化恢复消息
     */
    private function formatRestoreMessage(array $effects): string
    {
        $restoreText = [];

        if ($effects['hp'] > 0) {
            $restoreText[] = "{$effects['hp']} 点生命值";
        }
        if ($effects['mana'] > 0) {
            $restoreText[] = "{$effects['mana']} 点法力值";
        }

        return implode('和', $restoreText);
    }

    /**
     * 排序物品
     */
    private function sortItems($query, string $sortBy)
    {
        return match ($sortBy) {
            'quality' => $query->orderByDesc('quality')
                ->orderBy('definition_id')
                ->orderByDesc('quantity')
                ->get(),
            'price' => $query->orderByDesc(\DB::raw('COALESCE(sell_price, 0) * quantity'))
                ->orderBy('definition_id')
                ->orderByDesc('quantity')
                ->get(),
            default => $query->orderBy('definition_id')
                ->orderByDesc('quality')
                ->orderByDesc('quantity')
                ->get(),
        };
    }

    /**
     * 获取可出售的物品（按品质）
     */
    private function getSellableItemsByQuality(GameCharacter $character, string $quality)
    {
        return $character->items()
            ->where('is_in_storage', false)
            ->where('quality', $quality)
            ->whereHas('definition', function ($query) {
                $query->whereNotIn('type', ['potion', 'gem']);
            })
            ->with('definition')
            ->get();
    }

    /**
     * 清除背包缓存
     */
    private function clearInventoryCache(int $characterId): void
    {
        Cache::forget(self::CACHE_PREFIX . $characterId);
    }
}
