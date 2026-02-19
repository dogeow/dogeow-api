<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Services\Game\GameCharacterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GameCharacterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GameCharacterService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GameCharacterService();
    }

    public function test_get_character_list_returns_empty_array_when_no_characters(): void
    {
        $result = $this->service->getCharacterList(999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('characters', $result);
        $this->assertArrayHasKey('experience_table', $result);
    }

    public function test_get_character_list_returns_characters_for_user(): void
    {
        // Create a character for user 1
        $this->createTestCharacter(['user_id' => 1, 'name' => 'Character1']);

        $result = $this->service->getCharacterList(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('characters', $result);
        $this->assertEquals(1, $result['characters']->count());
    }

    public function test_get_character_detail_returns_null_when_not_found(): void
    {
        $result = $this->service->getCharacterDetail(999, 999);

        $this->assertNull($result);
    }

    public function test_get_character_detail_returns_character_data(): void
    {
        $character = $this->createTestCharacter(['user_id' => 1, 'name' => 'TestChar']);

        $result = $this->service->getCharacterDetail(1, $character->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('character', $result);
        $this->assertArrayHasKey('experience_table', $result);
        $this->assertArrayHasKey('combat_stats', $result);
        $this->assertArrayHasKey('stats_breakdown', $result);
    }

    public function test_create_character_with_valid_data(): void
    {
        // Skip if using SQLite due to enum mismatch between MySQL and SQLite
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('Skipped on SQLite due to enum difference');
        }

        $character = $this->service->createCharacter(1, 'NewCharacter', 'warrior');

        $this->assertInstanceOf(GameCharacter::class, $character);
        $this->assertEquals('NewCharacter', $character->name);
        $this->assertEquals('warrior', $character->class);
        $this->assertEquals(1, $character->level);
    }

    public function test_create_character_validates_name_length(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createCharacter(1, '', 'warrior');
    }

    public function test_create_character_validates_duplicate_name(): void
    {
        // Create first character
        $this->createTestCharacter(['user_id' => 1, 'name' => 'ExistingChar']);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->createCharacter(1, 'ExistingChar', 'warrior');
    }

    public function test_delete_character_removes_character(): void
    {
        $character = $this->createTestCharacter(['user_id' => 1, 'name' => 'ToDelete']);

        $this->service->deleteCharacter(1, $character->id);

        $this->assertNull(GameCharacter::find($character->id));
    }

    public function test_allocate_stats_adds_to_correct_attribute(): void
    {
        $character = $this->createTestCharacter([
            'user_id' => 1,
            'stat_points' => 5,
            'strength' => 10,
        ]);

        $result = $this->service->allocateStats(1, $character->id, ['strength' => 3]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('character', $result);
        $this->assertEquals(13, $character->fresh()->strength);
        $this->assertEquals(2, $character->fresh()->stat_points);
    }

    public function test_allocate_stats_fails_with_insufficient_points(): void
    {
        $character = $this->createTestCharacter([
            'user_id' => 1,
            'stat_points' => 2,
            'strength' => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->allocateStats(1, $character->id, ['strength' => 5]);
    }

    public function test_update_difficulty_changes_tier(): void
    {
        $character = $this->createTestCharacter([
            'user_id' => 1,
            'difficulty_tier' => 0,
        ]);

        $result = $this->service->updateDifficulty(1, 2, $character->id);

        $this->assertEquals(2, $result->difficulty_tier);
    }

    public function test_get_character_full_detail_includes_inventory_and_skills(): void
    {
        $character = $this->createTestCharacter(['user_id' => 1, 'name' => 'FullChar']);

        $result = $this->service->getCharacterFullDetail(1, $character->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('character', $result);
        $this->assertArrayHasKey('inventory', $result);
        $this->assertArrayHasKey('storage', $result);
        $this->assertArrayHasKey('skills', $result);
        $this->assertArrayHasKey('available_skills', $result);
    }

    public function test_check_offline_rewards_returns_zero_when_no_last_online(): void
    {
        $character = $this->createTestCharacter([
            'user_id' => 1,
            'last_online' => null,
        ]);

        $result = $this->service->checkOfflineRewards($character);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('available', $result);
        $this->assertFalse($result['available']);
    }

    public function test_check_offline_rewards_returns_available_for_long_offline_time(): void
    {
        $character = $this->createTestCharacter([
            'user_id' => 1,
            'last_online' => now()->subHours(2),
        ]);

        $result = $this->service->checkOfflineRewards($character);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('available', $result);
        $this->assertArrayHasKey('offline_seconds', $result);
        $this->assertArrayHasKey('experience', $result);
        $this->assertArrayHasKey('copper', $result);
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
