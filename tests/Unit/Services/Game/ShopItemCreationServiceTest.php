<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Services\Game\GameInventoryService;
use App\Services\Game\InventoryItemCalculator;
use App\Services\Game\ShopItemCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopItemCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ShopItemCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ShopItemCreationService;
    }

    public function test_create_potion_item_creates_new_item_with_correct_attributes(): void
    {
        $character = GameCharacter::factory()->create();
        $definition = GameItemDefinition::factory()->create([
            'type' => 'potion',
            'name' => 'Health Potion',
        ]);
        $randomStats = ['hp_restore' => 100];
        $quantity = 5;

        $item = $this->service->createPotionItem($character, $definition, $quantity, $randomStats);

        $this->assertInstanceOf(GameItem::class, $item);
        $this->assertEquals($character->id, $item->character_id);
        $this->assertEquals($definition->id, $item->definition_id);
        $this->assertEquals('common', $item->quality);
        $this->assertEquals($randomStats, $item->stats);
        $this->assertEquals($quantity, $item->quantity);
        $this->assertFalse($item->is_in_storage);
        $this->assertNotNull($item->sell_price);
    }

    public function test_create_potion_item_assigns_empty_slot_index(): void
    {
        $character = GameCharacter::factory()->create();
        $definition = GameItemDefinition::factory()->create([
            'type' => 'potion',
        ]);

        $item = $this->service->createPotionItem($character, $definition, 1, []);

        $this->assertNotNull($item->slot_index);
        $this->assertIsInt($item->slot_index);
    }

    public function test_create_equipment_items_creates_multiple_items(): void
    {
        $character = GameCharacter::factory()->create();
        $definition = GameItemDefinition::factory()->create([
            'type' => 'weapon',
            'sub_type' => 'sword',
        ]);
        $randomStats = ['attack' => 50];
        $quantity = 3;

        $items = $this->service->createEquipmentItems($character, $definition, $quantity, $randomStats);

        $this->assertCount(3, $items);
        foreach ($items as $item) {
            $this->assertInstanceOf(GameItem::class, $item);
            $this->assertEquals($character->id, $item->character_id);
            $this->assertEquals(1, $item->quantity);
        }
    }

    public function test_create_equipment_items_assigns_different_slot_indices(): void
    {
        $character = GameCharacter::factory()->create();
        $definition = GameItemDefinition::factory()->create([
            'type' => 'weapon',
            'sub_type' => 'sword',
        ]);

        $items = $this->service->createEquipmentItems($character, $definition, 3, []);

        $slotIndices = array_map(fn ($item) => $item->slot_index, $items);
        $this->assertCount(3, array_unique($slotIndices));
    }

    public function test_add_potion_to_inventory_increments_existing_item_quantity(): void
    {
        $character = GameCharacter::factory()->create();
        $definition = GameItemDefinition::factory()->create([
            'type' => 'potion',
        ]);
        $existingItem = GameItem::factory()->create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'quantity' => 5,
            'is_in_storage' => false,
        ]);
        $randomStats = [];

        $result = $this->service->addPotionToInventory($character, $definition, 3, $randomStats);

        $this->assertFalse($result['is_new']);
        $this->assertEquals($existingItem->id, $result['item']->id);
        $this->assertEquals(8, $result['item']->quantity);
    }

    public function test_add_potion_to_inventory_creates_new_item_when_not_exists(): void
    {
        $character = GameCharacter::factory()->create();
        $definition = GameItemDefinition::factory()->create([
            'type' => 'potion',
        ]);
        $randomStats = [];

        $result = $this->service->addPotionToInventory($character, $definition, 2, $randomStats);

        $this->assertTrue($result['is_new']);
        $this->assertEquals(2, $result['item']->quantity);
    }

    public function test_add_potion_to_inventory_does_not_merge_different_quality(): void
    {
        $character = GameCharacter::factory()->create();
        $definition = GameItemDefinition::factory()->create([
            'type' => 'potion',
        ]);
        GameItem::factory()->create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'rare',
            'is_in_storage' => false,
        ]);

        $result = $this->service->addPotionToInventory($character, $definition, 1, []);

        $this->assertTrue($result['is_new']);
    }

    public function test_add_potion_to_inventory_does_not_merge_storage_items(): void
    {
        $character = GameCharacter::factory()->create();
        $definition = GameItemDefinition::factory()->create([
            'type' => 'potion',
        ]);
        GameItem::factory()->create([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'is_in_storage' => true,
        ]);

        $result = $this->service->addPotionToInventory($character, $definition, 1, []);

        $this->assertTrue($result['is_new']);
    }

    public function test_has_inventory_space_returns_true_when_space_available(): void
    {
        $character = GameCharacter::factory()->create();
        GameItem::factory()->count(5)->create([
            'character_id' => $character->id,
            'is_in_storage' => false,
        ]);

        $result = $this->service->hasInventorySpace($character, 10);

        $this->assertTrue($result);
    }

    public function test_has_inventory_space_returns_false_when_full(): void
    {
        $character = GameCharacter::factory()->create();
        GameItem::factory()->count(100)->create([
            'character_id' => $character->id,
            'is_in_storage' => false,
        ]);

        $result = $this->service->hasInventorySpace($character, 1);

        $this->assertFalse($result);
    }

    public function test_has_inventory_space_for_potion_checks_count_not_space(): void
    {
        $character = GameCharacter::factory()->create();
        GameItem::factory()->count(99)->create([
            'character_id' => $character->id,
            'is_in_storage' => false,
        ]);

        $result = $this->service->hasInventorySpace($character, 1, true);

        $this->assertTrue($result);
    }
}
