<?php

namespace Tests\Unit\Controllers\Concerns;

use App\Http\Controllers\Concerns\CharacterConcern;
use App\Models\Game\GameCharacter;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class CharacterConcernTest extends TestCase
{
    public function test_get_character_returns_owned_character_from_query_parameter(): void
    {
        $user = User::factory()->create();
        $character = GameCharacter::create([
            'user_id' => $user->id,
            'name' => 'OwnedHero',
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 1,
            'experience' => 0,
            'copper' => 0,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'stat_points' => 0,
        ]);
        GameCharacter::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'OtherHero',
            'class' => 'mage',
            'gender' => 'female',
            'level' => 1,
            'experience' => 0,
            'copper' => 0,
            'strength' => 8,
            'dexterity' => 8,
            'vitality' => 8,
            'energy' => 12,
            'skill_points' => 0,
            'stat_points' => 0,
        ]);

        $request = Request::create('/api/rpg/test', 'GET', ['character_id' => $character->id]);
        $request->setUserResolver(fn () => $user);

        $controller = new class
        {
            use CharacterConcern;

            public function resolveCharacter(Request $request): GameCharacter
            {
                return $this->getCharacter($request);
            }

            public function resolveCharacterId(Request $request): ?int
            {
                return $this->getCharacterId($request);
            }
        };

        $resolved = $controller->resolveCharacter($request);

        $this->assertTrue($resolved->is($character));
        $this->assertSame($character->id, $controller->resolveCharacterId($request));
    }

    public function test_get_character_id_reads_input_when_query_is_missing(): void
    {
        $request = Request::create('/api/rpg/test', 'POST', ['character_id' => '12']);

        $controller = new class
        {
            use CharacterConcern;

            public function resolveCharacterId(Request $request): ?int
            {
                return $this->getCharacterId($request);
            }
        };

        $this->assertSame(12, $controller->resolveCharacterId($request));
    }
}
