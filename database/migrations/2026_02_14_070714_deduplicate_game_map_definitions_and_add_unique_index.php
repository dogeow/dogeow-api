<?php

use App\Models\Game\GameCharacter;
use App\Models\Game\GameCharacterMap;
use App\Models\Game\GameCombatLog;
use App\Models\Game\GameMapDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $maps = GameMapDefinition::query()->get();
        $grouped = $maps->groupBy(fn (GameMapDefinition $m) => $m->name.'|'.$m->act);

        $duplicateIdToKeeper = [];
        foreach ($grouped as $group) {
            $keeperId = $group->min('id');
            foreach ($group as $map) {
                if ($map->id !== $keeperId) {
                    $duplicateIdToKeeper[$map->id] = $keeperId;
                }
            }
        }

        foreach ($duplicateIdToKeeper as $duplicateId => $keeperId) {
            // 先处理 character_maps：若 (character_id, keeper_id) 已存在则删除重复行，否则更新 map_id
            GameCharacterMap::query()
                ->where('map_id', $duplicateId)
                ->get()
                ->each(function (GameCharacterMap $row) use ($keeperId) {
                    $exists = GameCharacterMap::query()
                        ->where('character_id', $row->character_id)
                        ->where('map_id', $keeperId)
                        ->exists();
                    if ($exists) {
                        $row->delete();
                    } else {
                        $row->update(['map_id' => $keeperId]);
                    }
                });
            GameCombatLog::query()->where('map_id', $duplicateId)->update(['map_id' => $keeperId]);
            GameCharacter::query()->where('current_map_id', $duplicateId)->update(['current_map_id' => $keeperId]);
        }

        if (! empty($duplicateIdToKeeper)) {
            GameMapDefinition::query()->whereIn('id', array_keys($duplicateIdToKeeper))->delete();
        }

        Schema::table('game_map_definitions', function (Blueprint $table) {
            $table->unique(['name', 'act']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_map_definitions', function (Blueprint $table) {
            $table->dropUnique(['name', 'act']);
        });
    }
};
