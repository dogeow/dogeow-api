<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;

class GamePotionService
{
    /**
     * Try to automatically use potions based on HP/MANA thresholds
     *
     * @return array List of potions used
     */
    public function tryAutoUsePotions(GameCharacter $character, int $currentHp, int $currentMana, array $charStats): array
    {
        $used = [];

        $hpThreshold = (int) ($character->hp_potion_threshold ?? 30);
        $hpThreshold = max(1, min(100, $hpThreshold));
        if ($character->auto_use_hp_potion && ($charStats['max_hp'] ?? 0) > 0) {
            $hpPercent = ($currentHp / $charStats['max_hp']) * 100;
            if ($hpPercent <= $hpThreshold) {
                $potion = $this->findBestPotion($character, 'hp');
                if ($potion) {
                    $this->usePotionItem($character, $potion);
                    $used['hp'] = [
                        'name' => $potion->definition->name,
                        'restored' => $potion->definition->base_stats['max_hp'] ?? 0,
                    ];
                }
            }
        }

        $mpThreshold = (int) ($character->mp_potion_threshold ?? 30);
        $mpThreshold = max(1, min(100, $mpThreshold));
        if ($character->auto_use_mp_potion && ($charStats['max_mana'] ?? 0) > 0) {
            $mpPercent = ($currentMana / $charStats['max_mana']) * 100;
            if ($mpPercent <= $mpThreshold) {
                $potion = $this->findBestPotion($character, 'mp');
                if ($potion) {
                    $this->usePotionItem($character, $potion);
                    $used['mp'] = [
                        'name' => $potion->definition->name,
                        'restored' => $potion->definition->base_stats['max_mana'] ?? 0,
                    ];
                }
            }
        }

        return $used;
    }

    /**
     * Find the best potion for the given type
     */
    public function findBestPotion(GameCharacter $character, string $type): ?GameItem
    {
        $statKey = $type === 'hp' ? 'max_hp' : 'max_mana';

        return $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')
                    ->where('sub_type', $type);
            })
            ->with('definition')
            ->get()
            ->sortByDesc(fn ($item) => $item->definition->base_stats[$statKey] ?? 0)
            ->first();
    }

    /**
     * Use a potion item
     */
    public function usePotionItem(GameCharacter $character, GameItem $potion): void
    {
        $stats = $potion->definition->base_stats ?? [];
        $hpRestored = $stats['max_hp'] ?? 0;
        $manaRestored = $stats['max_mana'] ?? 0;

        if ($hpRestored > 0) {
            $character->restoreHp($hpRestored);
        }
        if ($manaRestored > 0) {
            $character->restoreMana($manaRestored);
        }

        $potion->quantity > 1 ? $potion->decrement('quantity') : $potion->delete();
    }

    /**
     * Check if character has any potions of a specific type
     */
    public function hasPotion(GameCharacter $character, string $type): bool
    {
        return $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion')
                    ->where('sub_type', $type);
            })
            ->exists();
    }

    /**
     * Get potion inventory count
     */
    public function getPotionCount(GameCharacter $character, ?string $type = null): int
    {
        $query = $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) use ($type) {
                $query->where('type', 'potion');
                if ($type !== null) {
                    $query->where('sub_type', $type);
                }
            });

        return $query->count();
    }

    /**
     * Get all potions in inventory
     */
    public function getAllPotions(GameCharacter $character): array
    {
        $potions = $character->items()
            ->where('is_in_storage', false)
            ->whereHas('definition', function ($query) {
                $query->where('type', 'potion');
            })
            ->with('definition')
            ->get();

        return $potions->map(function ($potion) {
            return [
                'id' => $potion->id,
                'type' => $potion->definition->sub_type,
                'name' => $potion->definition->name,
                'quantity' => $potion->quantity,
                'restore_hp' => $potion->definition->base_stats['max_hp'] ?? 0,
                'restore_mp' => $potion->definition->base_stats['max_mana'] ?? 0,
            ];
        })->toArray();
    }
}
