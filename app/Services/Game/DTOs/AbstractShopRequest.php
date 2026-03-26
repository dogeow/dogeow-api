<?php

namespace App\Services\Game\DTOs;

use App\Models\Game\GameCharacter;

/**
 * Abstract base class for shop request DTOs - encapsulates shared parameters
 * to eliminate Long Parameter List anti-pattern.
 */
abstract readonly class AbstractShopRequest
{
    public function __construct(
        public GameCharacter $character,
        public int $itemId,
        public int $quantity = 1,
        public ?string $idempotencyKey = null,
    ) {}

    public function hasIdempotencyKey(): bool
    {
        return $this->idempotencyKey !== null && $this->idempotencyKey !== '';
    }
}
