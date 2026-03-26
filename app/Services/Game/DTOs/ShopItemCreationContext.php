<?php

namespace App\Services\Game\DTOs;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItemDefinition;

/**
 * Context DTO for shop item creation operations - encapsulates creation parameters
 * to eliminate Long Parameter List anti-pattern.
 */
readonly class ShopItemCreationContext
{
    /**
     * @param  callable(int): ?int  $findEmptySlotCallback  Callback to find empty slot
     */
    public function __construct(
        public GameCharacter $character,
        public GameItemDefinition $definition,
        public int $quantity,
        public array $randomStats,
        public $findEmptySlotCallback,
    ) {}

    public function findEmptySlot(): ?int
    {
        return ($this->findEmptySlotCallback)($this->character->id);
    }
}
