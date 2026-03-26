<?php

namespace App\Services\Game\DTOs;

use App\Models\Game\GameCharacter;

/**
 * ShopPurchaseRequest DTO - encapsulates all parameters for shop purchase
 */
readonly class ShopPurchaseRequest extends AbstractShopRequest
{
    public function __construct(
        GameCharacter $character,
        int $itemId,
        int $quantity = 1,
        ?string $idempotencyKey = null,
    ) {
        parent::__construct($character, $itemId, $quantity, $idempotencyKey);
    }

    public static function create(
        GameCharacter $character,
        int $itemId,
        int $quantity = 1,
        ?string $idempotencyKey = null,
    ): self {
        return new self(
            character: $character,
            itemId: $itemId,
            quantity: $quantity,
            idempotencyKey: $idempotencyKey,
        );
    }
}
