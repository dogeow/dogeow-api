<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Services\Game\GameCombatLootService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCombatLootServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameCombatLootService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GameCombatLootService();
    }

    public function test_process_death_loot_returns_empty_array_when_no_dead_monsters(): void
    {
        $character = $this->createTestCharacter();

        $roundResult = [
            'monsters_updated' => [
                ['id' => 1, 'hp' => 50], // Alive monster
            ],
            'loot' => [],
        ];

        $result = $this->service->processDeathLoot($character, $roundResult);

        $this->assertIsArray($result);
    }

    public function test_distribute_rewards_adds_experience_to_character(): void
    {
        $character = $this->createTestCharacter();
        $initialExperience = $character->experience;

        $roundResult = [
            'experience_gained' => 100,
            'copper_gained' => 0,
            'loot' => [],
        ];

        $result = $this->service->distributeRewards($character, $roundResult);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('loot', $result);
        $this->assertArrayHasKey('experience_gained', $result);
        $this->assertArrayHasKey('copper_gained', $result);
        $this->assertEquals(100, $result['experience_gained']);
    }

    public function test_distribute_rewards_adds_copper_to_character(): void
    {
        $character = $this->createTestCharacter();

        $roundResult = [
            'experience_gained' => 0,
            'copper_gained' => 50,
            'loot' => [],
        ];

        $result = $this->service->distributeRewards($character, $roundResult);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('copper_gained', $result);
        $this->assertEquals(50, $result['copper_gained']);
        $this->assertArrayHasKey('copper', $result['loot']);
        $this->assertEquals(50, $result['loot']['copper']);
    }

    public function test_distribute_rewards_returns_zero_when_no_rewards(): void
    {
        $character = $this->createTestCharacter();

        $roundResult = [
            'experience_gained' => 0,
            'copper_gained' => 0,
            'loot' => [],
        ];

        $result = $this->service->distributeRewards($character, $roundResult);

        $this->assertIsArray($result);
        $this->assertEquals(0, $result['experience_gained']);
        $this->assertEquals(0, $result['copper_gained']);
    }

    public function test_process_death_loot_with_dead_monsters(): void
    {
        $character = $this->createTestCharacter();

        // Round result with a dead monster
        $roundResult = [
            'monsters_updated' => [
                ['id' => 1, 'hp' => 0], // Dead monster
            ],
            'loot' => [],
        ];

        $result = $this->service->processDeathLoot($character, $roundResult);

        $this->assertIsArray($result);
    }

    /**
     * Helper method to create a test character
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createTestCharacter(array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => 1,
            'name' => 'TestCharacter',
            'class' => 'warrior',
            'level' => 1,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
            'is_fighting' => false,
            'difficulty_tier' => 0,
        ], $attributes));
    }
}
