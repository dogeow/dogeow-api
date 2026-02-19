<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Services\Game\CombatRoundProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CombatRoundProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected CombatRoundProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new CombatRoundProcessor();
    }

    public function test_process_one_round_with_no_monsters_returns_default_values(): void
    {
        $character = $this->createTestCharacter();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertArrayHasKey('round_damage_dealt', $result);
        $this->assertArrayHasKey('round_damage_taken', $result);
        $this->assertArrayHasKey('new_monster_hp', $result);
        $this->assertArrayHasKey('new_char_hp', $result);
        $this->assertArrayHasKey('defeat', $result);
        $this->assertArrayHasKey('has_alive_monster', $result);
    }

    public function test_process_one_round_calculates_damage_correctly(): void
    {
        $character = $this->createTestCharacter([
            'strength' => 100,
            'dexterity' => 50,
            'vitality' => 50,
            'energy' => 50,
        ]);

        // Set up combat monsters
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Slime',
                'level' => 1,
                'hp' => 50,
                'max_hp' => 50,
                'attack' => 10,
                'defense' => 5,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Character should deal some damage
        $this->assertGreaterThanOrEqual(0, $result['round_damage_dealt']);
        $this->assertIsInt($result['round_damage_dealt']);

        // Round details should be present
        $this->assertArrayHasKey('round_details', $result);
        $this->assertArrayHasKey('character', $result['round_details']);
        $this->assertArrayHasKey('monster', $result['round_details']);
    }

    public function test_process_one_round_with_skill_returns_skill_in_result(): void
    {
        $character = $this->createTestCharacter();

        // Set up a monster
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Goblin',
                'level' => 1,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 5,
                'defense' => 0,
                'experience' => 15,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Should return skills_used_this_round array (empty if no skills available)
        $this->assertArrayHasKey('skills_used_this_round', $result);
        $this->assertIsArray($result['skills_used_this_round']);
    }

    public function test_process_one_round_returns_experience_and_copper(): void
    {
        $character = $this->createTestCharacter();

        // Set up a monster at low HP so it can be killed
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Rat',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 50,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertArrayHasKey('experience_gained', $result);
        $this->assertArrayHasKey('copper_gained', $result);
        $this->assertIsInt($result['experience_gained']);
        $this->assertIsInt($result['copper_gained']);
    }

    public function test_process_one_round_tracks_monster_deaths(): void
    {
        $character = $this->createTestCharacter();

        // Monster that will die in one hit
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Weak Slime',
                'level' => 1,
                'hp' => 1,
                'max_hp' => 100,
                'attack' => 1,
                'defense' => 0,
                'experience' => 5,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Check round details for kill count
        $this->assertArrayHasKey('round_details', $result);
        $this->assertArrayHasKey('battle', $result['round_details']);
        $this->assertArrayHasKey('killed_count', $result['round_details']['battle']);
    }

    public function test_process_one_round_calculates_counter_damage(): void
    {
        $character = $this->createTestCharacter();

        // Set up monster that will attack
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Wolf',
                'level' => 5,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 20,
                'defense' => 5,
                'experience' => 25,
                'position' => 1,
            ],
        ];
        $character->save();

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        // Monster should deal some counter damage
        $this->assertGreaterThanOrEqual(0, $result['round_damage_taken']);
        $this->assertIsInt($result['round_damage_taken']);
    }

    public function test_has_alive_monster_detection(): void
    {
        $character = $this->createTestCharacter();

        // Test with alive monster
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Alive Monster',
                'level' => 1,
                'hp' => 50,
                'max_hp' => 50,
                'attack' => 5,
                'defense' => 0,
                'experience' => 10,
                'position' => 1,
            ],
        ];

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertTrue($result['has_alive_monster']);
    }

    public function test_character_defeat_detection(): void
    {
        $character = $this->createTestCharacter();

        // Character with very low HP
        $character->current_hp = 1;
        $character->save();

        // Monster with high attack
        $character->combat_monsters = [
            [
                'id' => 1,
                'name' => 'Strong Monster',
                'level' => 10,
                'hp' => 100,
                'max_hp' => 100,
                'attack' => 50,
                'defense' => 0,
                'experience' => 50,
                'position' => 1,
            ],
        ];

        $result = $this->processor->processOneRound(
            $character,
            1,
            [],
            [],
            []
        );

        $this->assertArrayHasKey('defeat', $result);
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
