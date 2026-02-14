<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Game\GameCharacter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterController extends Controller
{
    /**
     * 获取当前用户的角色列表
     */
    public function index(Request $request): JsonResponse
    {
        $characters = GameCharacter::query()
            ->where('user_id', $request->user()->id)
            ->get();

        foreach ($characters as $character) {
            $character->reconcileLevelFromExperience();
        }

        return $this->success([
            'characters' => $characters->map(fn ($c) => $c->only(['id', 'name', 'class', 'level', 'experience', 'copper', 'is_fighting', 'difficulty_tier'])),
            'experience_table' => GameCharacter::EXPERIENCE_TABLE,
        ]);
    }

    /**
     * 获取指定角色信息
     */
    public function show(Request $request): JsonResponse
    {
        $characterId = $request->query('character_id');

        $query = GameCharacter::query()->where('user_id', $request->user()->id);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        $character = $query->with(['equipment.item.definition', 'skills.skill', 'currentMap'])->first();

        if (! $character) {
            return $this->success(['character' => null]);
        }

        // 根据当前经验重算等级，避免经验已达标但等级未更新的情况
        $character->reconcileLevelFromExperience();

        return $this->success([
            'character' => $character,
            'experience_table' => GameCharacter::EXPERIENCE_TABLE,
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'equipped_items' => $character->getEquippedItems(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ]);
    }

    /**
     * 创建新角色
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:32|alpha_num',
            'class' => 'required|in:warrior,mage,ranger',
        ]);

        $userId = $request->user()->id;

        // 检查角色名是否已存在
        if (GameCharacter::query()->where('name', $validated['name'])->exists()) {
            return $this->error('角色名已被使用');
        }

        // 获取职业基础属性
        $baseStats = GameCharacter::CLASS_BASE_STATS[$validated['class']];

        // 创建角色
        $character = GameCharacter::create([
            'user_id' => $userId,
            'name' => $validated['name'],
            'class' => $validated['class'],
            'level' => 1,
            'experience' => 0,
            'copper' => 0,
            'strength' => $baseStats['strength'],
            'dexterity' => $baseStats['dexterity'],
            'vitality' => $baseStats['vitality'],
            'energy' => $baseStats['energy'],
            'skill_points' => 0,
            'stat_points' => 0,
        ]);

        // 初始化装备槽位
        foreach (GameCharacter::SLOTS as $slot) {
            $character->equipment()->create(['slot' => $slot]);
        }

        // 解锁初始地图 - 使用数据库中实际存在的第一张地图
        $firstMap = \App\Models\Game\GameMapDefinition::orderBy('id')->first();
        if ($firstMap) {
            $character->mapProgress()->create([
                'map_id' => $firstMap->id,
            ]);
        }

        return $this->success([
            'character' => $character->fresh(['equipment', 'skills', 'currentMap']),
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ], '角色创建成功', 201);
    }

    /**
     * 删除角色（仅限本人，关联数据由外键级联删除）
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character_id' => 'required|integer|exists:game_characters,id',
        ]);

        $character = GameCharacter::query()
            ->where('id', $validated['character_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $character->delete();

        return $this->success(null, '角色已删除');
    }

    /**
     * 分配属性点
     */
    public function allocateStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character_id' => 'required|integer|exists:game_characters,id',
            'strength' => 'sometimes|integer|min:0',
            'dexterity' => 'sometimes|integer|min:0',
            'vitality' => 'sometimes|integer|min:0',
            'energy' => 'sometimes|integer|min:0',
        ]);

        $character = GameCharacter::query()
            ->where('id', $validated['character_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $totalPoints = ($validated['strength'] ?? 0) +
                     ($validated['dexterity'] ?? 0) +
                     ($validated['vitality'] ?? 0) +
                     ($validated['energy'] ?? 0);

        if ($totalPoints > $character->stat_points) {
            return $this->error('属性点不足');
        }

        $character->strength += $validated['strength'] ?? 0;
        $character->dexterity += $validated['dexterity'] ?? 0;
        $character->vitality += $validated['vitality'] ?? 0;
        $character->energy += $validated['energy'] ?? 0;
        $character->stat_points -= $totalPoints;
        $character->save();

        return $this->success([
            'character' => $character,
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ], '属性分配成功');
    }

    /**
     * 更新难度（普通/专家/地狱1/地狱2...）
     */
    public function updateDifficulty(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character_id' => 'sometimes|integer|exists:game_characters,id',
            'difficulty_tier' => 'required|integer|min:0|max:9',
        ]);

        $query = GameCharacter::query()->where('user_id', $request->user()->id);
        if (isset($validated['character_id'])) {
            $query->where('id', $validated['character_id']);
        }
        $character = $query->firstOrFail();

        $character->difficulty_tier = $validated['difficulty_tier'];
        $character->save();

        return $this->success([
            'character' => $character,
        ], '难度已更新');
    }

    /**
     * 获取角色详细信息（背包、技能等）
     */
    public function detail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'character_id' => 'sometimes|integer|exists:game_characters,id',
        ]);

        $query = GameCharacter::query()->where('user_id', $request->user()->id);

        if (isset($validated['character_id'])) {
            $query->where('id', $validated['character_id']);
        }

        $character = $query->firstOrFail();

        $inventory = $character->items()
            ->where('is_in_storage', false)
            ->with('definition')
            ->orderBy('slot_index')
            ->get();

        $storage = $character->items()
            ->where('is_in_storage', true)
            ->with('definition')
            ->orderBy('slot_index')
            ->get();

        $skills = $character->skills()
            ->with('skill')
            ->orderBy('slot_index')
            ->get();

        $availableSkills = \App\Models\Game\GameSkillDefinition::query()
            ->where('is_active', true)
            ->where(function ($query) use ($character) {
                $query->where('class_restriction', 'all')
                    ->orWhere('class_restriction', $character->class);
            })
            ->get();

        return $this->success([
            'character' => $character,
            'inventory' => $inventory,
            'storage' => $storage,
            'skills' => $skills,
            'available_skills' => $availableSkills,
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ]);
    }
}
