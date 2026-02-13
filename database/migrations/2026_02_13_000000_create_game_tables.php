<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 游戏角色表
        Schema::create('game_characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32);
            $table->enum('class', ['warrior', 'mage', 'ranger'])->default('warrior');
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedBigInteger('experience')->default(0);
            $table->unsignedBigInteger('gold')->default(0);
            $table->unsignedInteger('strength')->default(10);
            $table->unsignedInteger('dexterity')->default(10);
            $table->unsignedInteger('vitality')->default(10);
            $table->unsignedInteger('energy')->default(10);
            $table->unsignedTinyInteger('skill_points')->default(0);
            $table->unsignedTinyInteger('stat_points')->default(0);
            $table->unsignedSmallInteger('current_map_id')->nullable();
            $table->boolean('is_fighting')->default(false);
            $table->timestamp('last_combat_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        // 游戏物品定义
        Schema::create('game_item_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->enum('type', [
                'weapon', 'helmet', 'armor', 'gloves', 'boots',
                'belt', 'ring', 'amulet', 'potion',
            ]);
            $table->enum('sub_type', [
                'sword', 'axe', 'mace', 'staff', 'bow', 'dagger',
                'cloth', 'leather', 'mail', 'plate',
            ])->nullable();
            $table->json('base_stats')->nullable();
            $table->unsignedTinyInteger('required_level')->default(1);
            $table->unsignedSmallInteger('required_strength')->default(0);
            $table->unsignedSmallInteger('required_dexterity')->default(0);
            $table->unsignedSmallInteger('required_energy')->default(0);
            $table->string('icon', 64)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 游戏物品实例（玩家背包/仓库）
        Schema::create('game_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('game_characters')->cascadeOnDelete();
            $table->foreignId('definition_id')->constrained('game_item_definitions');
            $table->enum('quality', ['common', 'magic', 'rare', 'legendary', 'mythic'])->default('common');
            $table->json('stats')->nullable();
            $table->json('affixes')->nullable();
            $table->boolean('is_in_storage')->default(false);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unsignedTinyInteger('slot_index')->nullable();
            $table->timestamps();
        });

        // 角色装备槽位
        Schema::create('game_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('game_characters')->cascadeOnDelete();
            $table->enum('slot', [
                'weapon', 'helmet', 'armor', 'gloves', 'boots',
                'belt', 'ring1', 'ring2', 'amulet',
            ]);
            $table->foreignId('item_id')->nullable()->constrained('game_items')->nullOnDelete();
            $table->timestamps();

            $table->unique(['character_id', 'slot']);
        });

        // 技能定义
        Schema::create('game_skill_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->text('description')->nullable();
            $table->enum('type', ['active', 'passive'])->default('active');
            $table->enum('class_restriction', ['warrior', 'mage', 'ranger', 'all'])->default('all');
            $table->unsignedTinyInteger('max_level')->default(10);
            $table->decimal('base_damage', 8, 2)->default(0);
            $table->decimal('damage_per_level', 8, 2)->default(0);
            $table->unsignedSmallInteger('mana_cost')->default(0);
            $table->unsignedSmallInteger('mana_cost_per_level')->default(0);
            $table->decimal('cooldown', 5, 2)->default(0);
            $table->string('icon', 64)->nullable();
            $table->json('effects')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 角色已学技能
        Schema::create('game_character_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('game_characters')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('game_skill_definitions');
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedTinyInteger('slot_index')->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'skill_id']);
        });

        // 地图定义
        Schema::create('game_map_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->unsignedTinyInteger('act')->default(1);
            $table->unsignedTinyInteger('min_level')->default(1);
            $table->unsignedTinyInteger('max_level')->default(10);
            $table->json('monster_ids')->nullable();
            $table->boolean('has_teleport')->default(false);
            $table->unsignedSmallInteger('teleport_cost')->default(0);
            $table->string('background', 128)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 角色地图进度
        Schema::create('game_character_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('game_characters')->cascadeOnDelete();
            $table->foreignId('map_id')->constrained('game_map_definitions');
            $table->boolean('unlocked')->default(false);
            $table->boolean('teleport_unlocked')->default(false);
            $table->timestamps();

            $table->unique(['character_id', 'map_id']);
        });

        // 怪物定义
        Schema::create('game_monster_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->enum('type', ['normal', 'elite', 'boss'])->default('normal');
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('hp_base')->default(100);
            $table->unsignedInteger('hp_per_level')->default(10);
            $table->unsignedInteger('attack_base')->default(10);
            $table->unsignedInteger('attack_per_level')->default(2);
            $table->unsignedInteger('defense_base')->default(5);
            $table->unsignedInteger('defense_per_level')->default(1);
            $table->unsignedInteger('experience_base')->default(10);
            $table->unsignedInteger('experience_per_level')->default(5);
            $table->json('drop_table')->nullable();
            $table->string('icon', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 战斗日志
        Schema::create('game_combat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('game_characters')->cascadeOnDelete();
            $table->foreignId('map_id')->constrained('game_map_definitions');
            $table->foreignId('monster_id')->constrained('game_monster_definitions');
            $table->unsignedInteger('damage_dealt')->default(0);
            $table->unsignedInteger('damage_taken')->default(0);
            $table->boolean('victory')->default(true);
            $table->json('loot_dropped')->nullable();
            $table->unsignedInteger('experience_gained')->default(0);
            $table->unsignedInteger('gold_gained')->default(0);
            $table->unsignedMediumInteger('duration_seconds')->default(0);
            $table->timestamps();

            $table->index(['character_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_combat_logs');
        Schema::dropIfExists('game_character_maps');
        Schema::dropIfExists('game_monster_definitions');
        Schema::dropIfExists('game_map_definitions');
        Schema::dropIfExists('game_character_skills');
        Schema::dropIfExists('game_skill_definitions');
        Schema::dropIfExists('game_items');
        Schema::dropIfExists('game_item_definitions');
        Schema::dropIfExists('game_equipment');
        Schema::dropIfExists('game_characters');
    }
};
