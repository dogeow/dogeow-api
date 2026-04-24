<?php

namespace Database\Seeders\Game;

use App\Models\Game\GameItemDefinition;
use App\Models\Game\GameMapDefinition;
use App\Models\Game\GameMonsterDefinition;
use App\Models\Game\GameSkillDefinition;
use Illuminate\Database\Seeder;

class GameFactorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GameItemDefinition::factory()->count(24)->create();
        GameSkillDefinition::factory()->count(12)->create();

        $monsters = GameMonsterDefinition::factory()->count(18)->create();

        GameMapDefinition::factory()->count(8)->create()->each(function (GameMapDefinition $map) use ($monsters): void {
            $selectionCount = random_int(2, min(4, $monsters->count()));

            $map->update([
                'monster_ids' => $monsters->random($selectionCount)->modelKeys(),
            ]);
        });
    }
}
