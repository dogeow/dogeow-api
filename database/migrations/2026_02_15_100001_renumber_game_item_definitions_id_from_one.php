<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 将 game_item_definitions.id 按当前顺序重排为 1, 2, 3, ...
     * 并更新 game_items.definition_id、game_item_definitions.icon 为 item_{id}.png。
     */
    public function up(): void
    {
        $rows = DB::table('game_item_definitions')->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return;
        }

        $oldToNew = [];
        foreach ($rows as $i => $r) {
            $oldToNew[(int) $r->id] = $i + 1;
        }

        $offset = 1000000;

        // 1. 更新 game_items.definition_id（先偏移再设回新 id，避免唯一约束冲突）
        foreach ($oldToNew as $oldId => $newId) {
            DB::table('game_items')->where('definition_id', $oldId)->update(['definition_id' => $offset + $oldId]);
        }
        foreach ($oldToNew as $oldId => $newId) {
            DB::table('game_items')->where('definition_id', $offset + $oldId)->update(['definition_id' => $newId]);
        }

        // 2. 更新 game_item_gems.gem_definition_id（若存在该表）
        if (Schema::hasTable('game_item_gems')) {
            foreach ($oldToNew as $oldId => $newId) {
                DB::table('game_item_gems')->where('gem_definition_id', $oldId)->update(['gem_definition_id' => $offset + $oldId]);
            }
            foreach ($oldToNew as $oldId => $newId) {
                DB::table('game_item_gems')->where('gem_definition_id', $offset + $oldId)->update(['gem_definition_id' => $newId]);
            }
        }

        // 3. 重排 game_item_definitions.id：先整体偏移再设回 1,2,3,...
        DB::statement('UPDATE game_item_definitions SET id = id + '.$offset);
        foreach ($rows as $i => $r) {
            $newId = $i + 1;
            $currentId = $offset + (int) $r->id;
            DB::table('game_item_definitions')->where('id', $currentId)->update([
                'id' => $newId,
                'icon' => 'item_'.$newId.'.png',
            ]);
        }
    }

    public function down(): void
    {
        // 不可逆：无法从 1,2,3 还原到原来的 id
    }
};
