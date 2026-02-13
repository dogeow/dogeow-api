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
            ->get(['id', 'name', 'class', 'level', 'experience', 'gold', 'is_fighting']);

        return $this->success([
            'characters' => $characters,
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

        return $this->success([
            'character' => $character,
            'combat_stats' => $character->getCombatStats(),
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
            'gold' => 100,
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

        // 解锁初始地图
        $character->mapProgress()->create([
            'map_id' => 1,
            'unlocked' => true,
            'teleport_unlocked' => true,
        ]);

        // 给予初始装备
        $starterItems = [
            'weapon' => ['warrior' => 1, 'mage' => 7, 'ranger' => 13], // 新手剑/法杖/弓
            'helmet' => 19, // 布帽
            'armor' => 25, // 布衣
            'gloves' => 37, // 布手套
            'boots' => 43, // 布鞋
            'belt' => 55, // 布腰带
            'ring1' => 67, // 铜戒指
            'ring2' => 67, // 铜戒指
            'amulet' => 76, // 木制护符
        ];

        foreach ($starterItems as $slot => $itemIds) {
            if (is_array($itemIds)) {
                // 根据职业选择武器
                $itemId = $itemIds[$validated['class']] ?? $itemIds['warrior'];
            } else {
                $itemId = $itemIds;
            }

            $itemDef = \App\Models\Game\GameItemDefinition::find($itemId);
            if ($itemDef) {
                $item = GameItem::create([
                    'character_id' => $character->id,
                    'item_definition_id' => $itemId,
                    'slot_index' => null,
                ]);

                // 装备到对应槽位
                $equipment = $character->equipment()->where('slot', $slot)->first();
                if ($equipment) {
                    $equipment->update(['game_item_id' => $item->id]);
                }
            }
        }

        return $this->success([
            'character' => $character->fresh(['equipment', 'skills', 'currentMap']),
            'combat_stats' => $character->getCombatStats(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ], '角色创建成功', 201);
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

        $totalPoints = array_sum($validated);

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
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ], '属性分配成功');
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
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ]);
    }
}
