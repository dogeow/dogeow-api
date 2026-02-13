<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameCharacterSkill extends Model
{
    protected $fillable = [
        'character_id',
        'skill_id',
        'level',
        'slot_index',
    ];

    /**
     * 获取所属角色
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }

    /**
     * 获取技能定义
     */
    public function skill(): BelongsTo
    {
        return $this->belongsTo(GameSkillDefinition::class, 'skill_id');
    }

    /**
     * 获取当前等级的伤害
     */
    public function getDamage(): float
    {
        return $this->skill->getDamageAtLevel($this->level);
    }

    /**
     * 获取当前等级的法力消耗
     */
    public function getManaCost(): int
    {
        return $this->skill->getManaCostAtLevel($this->level);
    }

    /**
     * 升级技能
     */
    public function levelUp(): bool
    {
        if ($this->level >= $this->skill->max_level) {
            return false;
        }

        $this->level++;
        $this->save();

        return true;
    }
}
