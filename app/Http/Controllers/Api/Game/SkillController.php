<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\LearnSkillRequest;
use App\Models\Game\GameSkillDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    use \App\Http\Controllers\Concerns\CharacterConcern;

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
    public function learn(LearnSkillRequest $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        if ($character->skill_points <= 0) {
            return $this->error('技能点不足');
        }

        $skill = GameSkillDefinition::findOrFail($request->input('skill_id'));

        // 检查职业限制
        if (! $skill->canLearnByClass($character->class)) {
            return $this->error('该技能不适合你的职业');
        }

        // 检查是否已学习
        $existingSkill = $character->skills()->where('skill_id', $skill->id)->first();
        if ($existingSkill) {
            return $this->error('已经学习了该技能');
        }

        // 学习技能
        $characterSkill = $character->skills()->create([
            'skill_id' => $skill->id,
        ]);
        $characterSkill->load('skill');

        $character->skill_points--;
        $character->save();

        return $this->success([
            'character' => $character,
            'skill_points' => $character->skill_points,
            'character_skill' => $characterSkill,
        ], '技能学习成功');
    }
}
