<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * 将 game_monster_definitions.id 按当前顺序重排为 1, 2, 3, ...
     * 并更新 game_combat_logs.monster_id、game_characters.combat_monster_id、game_map_definitions.monster_ids。
     */
    public function up(): void
    {
        $rows = DB::table('game_monster_definitions')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return;
        }

        $oldToNew = [];
        foreach ($rows as $i => $r) {
            $oldToNew[(int) $r->id] = $i + 1;
        }

        $offset = 1000000;

        // 1. 更新 game_combat_logs.monster_id
        foreach ($oldToNew as $oldId => $newId) {
            DB::table('game_combat_logs')->where('monster_id', $oldId)->update(['monster_id' => $offset + $oldId]);
        }
        foreach ($oldToNew as $oldId => $newId) {
            DB::table('game_combat_logs')->where('monster_id', $offset + $oldId)->update(['monster_id' => $newId]);
        }

        // 2. 更新 game_characters.combat_monster_id
        foreach ($oldToNew as $oldId => $newId) {
            DB::table('game_characters')->where('combat_monster_id', $oldId)->update(['combat_monster_id' => $offset + $oldId]);
        }
        foreach ($oldToNew as $oldId => $newId) {
            DB::table('game_characters')->where('combat_monster_id', $offset + $oldId)->update(['combat_monster_id' => $newId]);
        }

        // 3. 更新 game_map_definitions.monster_ids（JSON 数组）
        $maps = DB::table('game_map_definitions')->get();
        foreach ($maps as $map) {
            $ids = json_decode($map->monster_ids, true);
            if (! is_array($ids)) {
                continue;
            }
            $mapped = [];
            foreach ($ids as $oldId) {
                $mapped[] = $oldToNew[(int) $oldId] ?? $oldId;
            }
            DB::table('game_map_definitions')->where('id', $map->id)->update(['monster_ids' => json_encode(array_values($mapped))]);
        }

        // 4. 重排 game_monster_definitions.id：先整体偏移再设回 1,2,3,...
        DB::statement('UPDATE game_monster_definitions SET id = id + '.$offset);
        foreach ($rows as $i => $r) {
            $newId = $i + 1;
            $currentId = $offset + (int) $r->id;
            DB::table('game_monster_definitions')->where('id', $currentId)->update(['id' => $newId]);
        }
    }

    public function down(): void
    {
        // 不可逆：无法从 1,2,3 还原到原来的 285,286,...
    }
};
