<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameCharacterSkill;
use App\Models\Game\GameSkillDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    /**
     * 获取所有可用技能
     */
    public function index(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $availableSkills = GameSkillDefinition::query()
            ->where('is_active', true)
            ->where(function ($query) use ($character) {
                $query->where('class_restriction', 'all')
                    ->orWhere('class_restriction', $character->class);
            })
            ->get();

        $learnedSkills = $character->skills()
            ->with('skill')
            ->get();

        return $this->success([
            'available_skills' => $availableSkills,
            'learned_skills' => $learnedSkills,
            'skill_points' => $character->skill_points,
        ]);
    }

    /**
     * 学习技能
     */
    public function learn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_id' => 'required|integer|exists:game_skill_definitions,id',
        ]);

        $character = $this->getCharacter($request);

        if ($character->skill_points <= 0) {
            return $this->error('技能点不足');
        }

        $skill = GameSkillDefinition::findOrFail($validated['skill_id']);

        // 检查职业限制
        if (! $skill->canLearnByClass($character->class)) {
            return $this->error('该技能不适合你的职业');
        }

        // 检查是否已学习
        $existingSkill = $character->skills()->where('skill_id', $skill->id)->first();
        if ($existingSkill) {
            return $this->error('已经学习了该技能');
        }

        // 学习技能（直接学习到最高等级）
        $characterSkill = $character->skills()->create([
            'skill_id' => $skill->id,
            'level' => $skill->max_level,
        ]);

        $character->skill_points--;
        $character->save();

        return $this->success([
            'character_skill' => $characterSkill->load('skill'),
            'skill_points' => $character->skill_points,
        ], '技能学习成功');
    }

    /**
     * 装备技能到技能槽
     */
    public function slot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_id' => 'required|integer|exists:game_character_skills,id',
            'slot_index' => 'required|integer|min:1|max:6',
        ]);

        $character = $this->getCharacter($request);

        $characterSkill = GameCharacterSkill::query()
            ->where('id', $validated['skill_id'])
            ->where('character_id', $character->id)
            ->first();

        if (! $characterSkill) {
            return $this->error('技能不存在');
        }

        // 检查技能类型（只有主动技能可以装备到槽位）
        if ($characterSkill->skill->type !== 'active') {
            return $this->error('只有主动技能可以装备');
        }

        // 检查槽位是否已被占用
        $existingSkill = $character->skills()
            ->where('slot_index', $validated['slot_index'])
            ->where('id', '!=', $characterSkill->id)
            ->first();

        if ($existingSkill) {
            $existingSkill->slot_index = null;
            $existingSkill->save();
        }

        $characterSkill->slot_index = $validated['slot_index'];
        $characterSkill->save();

        return $this->success([
            'character_skill' => $characterSkill->fresh('skill'),
        ], '技能装备成功');
    }

    /**
     * 卸下技能
     */
    public function unslot(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skill_id' => 'required|integer|exists:game_character_skills,id',
        ]);

        $character = $this->getCharacter($request);

        $characterSkill = GameCharacterSkill::query()
            ->where('id', $validated['skill_id'])
            ->where('character_id', $character->id)
            ->first();

        if (! $characterSkill) {
            return $this->error('技能不存在');
        }

        $characterSkill->slot_index = null;
        $characterSkill->save();

        return $this->success([], '技能卸下成功');
    }

    /**
     * 获取角色
     */
    private function getCharacter(Request $request): GameCharacter
    {
        $characterId = $request->query('character_id') ?: $request->input('character_id');

        $query = GameCharacter::query()
            ->where('user_id', $request->user()->id);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        return $query->firstOrFail();
    }
}
