<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;

/**
 * ShopItemCreationService - handles item creation logic for shop transactions
 *
 * This service extracts the responsibility of creating GameItem records
 * from the shop service, following the Single Responsibility Principle.
 */
class ShopItemCreationService
{
    public function __construct(
        private readonly InventoryItemCalculator $itemCalculator
    ) {}

    /**
     * Create a potion item for purchase
     */
    public function createPotionItem(
        GameCharacter $character,
        GameItemDefinition $definition,
        int $quantity,
        array $randomStats,
        callable $findEmptySlotCallback
    ): GameItem {
        $tempItem = new GameItem([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $randomStats,
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => $quantity,
        ]);
        $sellPrice = $this->itemCalculator->calculateSellPrice($tempItem);

        return GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $randomStats,
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => $quantity,
            'slot_index' => $findEmptySlotCallback($character->id),
            'sell_price' => $sellPrice,
        ]);
    }

    /**
     * Create equipment items for purchase
     *
     * @return array<int, GameItem>
     */
    public function createEquipmentItems(
        GameCharacter $character,
        GameItemDefinition $definition,
        int $quantity,
        array $randomStats,
        callable $findEmptySlotCallback
    ): array {
        $tempItem = new GameItem([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $randomStats,
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
        ]);
        $sellPrice = $this->itemCalculator->calculateSellPrice($tempItem);
        $createdItems = [];

        for ($i = 0; $i < $quantity; $i++) {
            $createdItems[] = GameItem::create([
                'character_id' => $character->id,
                'definition_id' => $definition->id,
                'quality' => 'common',
                'stats' => $randomStats,
                'affixes' => [],
                'is_in_storage' => false,
                'quantity' => 1,
                'slot_index' => $findEmptySlotCallback($character->id),
                'sell_price' => $sellPrice,
            ]);
        }

        return $createdItems;
    }

    /**
     * Add quantity to existing potion item or create new one
     *
     * @return array{item: GameItem, is_new: bool}
     */
    public function addPotionToInventory(
        GameCharacter $character,
        GameItemDefinition $definition,
        int $quantity,
        array $randomStats,
        callable $findEmptySlotCallback
    ): array {
        /** @var GameItem|null $existingItem */
        $existingItem = $character->items()
            ->where('definition_id', $definition->id)
            ->where('is_in_storage', false)
            ->where('quality', 'common')
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->save();

            return ['item' => $existingItem, 'is_new' => false];
        }

        $item = $this->createPotionItem($character, $definition, $quantity, $randomStats, $findEmptySlotCallback);

        return ['item' => $item, 'is_new' => true];
    }

    /**
     * Check if character has inventory space for items
     */
    public function hasInventorySpace(GameCharacter $character, int $itemCount, bool $isPotion = false): bool
    {
        $inventoryCount = $character->items()->where('is_in_storage', false)->count();
        $inventorySize = GameInventoryService::INVENTORY_SIZE;

        if ($isPotion) {
            return $inventoryCount < $inventorySize;
        }

        return $inventoryCount + $itemCount <= $inventorySize;
    }
}
