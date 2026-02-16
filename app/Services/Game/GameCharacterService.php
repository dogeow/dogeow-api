<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use Illuminate\Support\Facades\DB;

class GameCharacterService
{
    /**
     * 获取用户角色列表
     */
    public function getCharacterList(int $userId): array
    {
        $characters = GameCharacter::query()
            ->where('user_id', $userId)
            ->get();

        foreach ($characters as $character) {
            $character->reconcileLevelFromExperience();
        }

        return [
            'characters' => $characters->map(fn ($c) => $c->only(['id', 'name', 'class', 'level', 'experience', 'copper', 'is_fighting', 'difficulty_tier'])),
            'experience_table' => config('game.experience_table', []),
        ];
    }

    /**
     * 获取角色详情
     */
    public function getCharacterDetail(int $userId, ?int $characterId = null): ?array
    {
        $query = GameCharacter::query()->where('user_id', $userId);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        $character = $query->with(['equipment.item.definition', 'skills.skill', 'currentMap'])->first();

        if (! $character) {
            return null;
        }

        $character->reconcileLevelFromExperience();

        return [
            'character' => $character,
            'experience_table' => config('game.experience_table', []),
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'equipped_items' => $character->getEquippedItems(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ];
    }

    /**
     * 创建角色
     */
    public function createCharacter(int $userId, string $name, string $class): GameCharacter
    {
        // 检查角色名是否已存在
        if (GameCharacter::query()->where('name', $name)->exists()) {
            throw new \InvalidArgumentException('角色名已被使用');
        }

        // 获取职业基础属性
        $classStats = config('game.class_base_stats', []);
        $baseStats = $classStats[$class] ?? ['strength' => 2, 'dexterity' => 3, 'vitality' => 2, 'energy' => 2];

        return DB::transaction(function () use ($userId, $name, $class, $baseStats) {
            // 创建角色
            $character = GameCharacter::create([
                'user_id' => $userId,
                'name' => $name,
                'class' => $class,
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
            foreach (GameCharacter::getSlots() as $slot) {
                $character->equipment()->create(['slot' => $slot]);
            }

            // 解锁初始地图
            $firstMap = GameMapDefinition::orderBy('id')->first();
            if ($firstMap) {
                $character->mapProgress()->create([
                    'map_id' => $firstMap->id,
                ]);
            }

            return $character;
        });
    }

    /**
     * 删除角色
     */
    public function deleteCharacter(int $userId, int $characterId): void
    {
        $character = GameCharacter::query()
            ->where('id', $characterId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $character->delete();
    }

    /**
     * 分配属性点
     */
    public function allocateStats(int $userId, int $characterId, array $stats): array
    {
        $character = GameCharacter::query()
            ->where('id', $characterId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $totalPoints = ($stats['strength'] ?? 0) +
                     ($stats['dexterity'] ?? 0) +
                     ($stats['vitality'] ?? 0) +
                     ($stats['energy'] ?? 0);

        if ($totalPoints > $character->stat_points) {
            throw new \InvalidArgumentException('属性点不足');
        }

        $character->strength += $stats['strength'] ?? 0;
        $character->dexterity += $stats['dexterity'] ?? 0;
        $character->vitality += $stats['vitality'] ?? 0;
        $character->energy += $stats['energy'] ?? 0;
        $character->stat_points -= $totalPoints;
        $character->save();

        return [
            'character' => $character,
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ];
    }

    /**
     * 更新难度
     */
    public function updateDifficulty(int $userId, int $difficultyTier, ?int $characterId = null): GameCharacter
    {
        $query = GameCharacter::query()->where('user_id', $userId);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        $character = $query->firstOrFail();
        $character->difficulty_tier = $difficultyTier;
        $character->save();

        return $character;
    }

    /**
     * 获取角色详细信息（包含背包、技能等）
     */
    public function getCharacterFullDetail(int $userId, ?int $characterId = null): array
    {
        $query = GameCharacter::query()->where('user_id', $userId);

        if ($characterId) {
            $query->where('id', $characterId);
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

        return [
            'character' => $character,
            'inventory' => $inventory,
            'storage' => $storage,
            'skills' => $skills,
            'available_skills' => $availableSkills,
            'combat_stats' => $character->getCombatStats(),
            'stats_breakdown' => $character->getCombatStatsBreakdown(),
            'current_hp' => $character->getCurrentHp(),
            'current_mana' => $character->getCurrentMana(),
        ];
    }

    /**
     * 检查离线奖励信息
     */
    public function checkOfflineRewards(GameCharacter $character): array
    {
        $lastOnline = $character->last_online;

        if (! $lastOnline) {
            return [
                'available' => false,
                'offline_seconds' => 0,
                'experience' => 0,
                'copper' => 0,
                'level_up' => false,
            ];
        }

        $now = now();
        $offlineSeconds = $now->diffInSeconds($lastOnline);

        // 最小60秒才发放离线奖励
        if ($offlineSeconds < 60) {
            return [
                'available' => false,
                'offline_seconds' => $offlineSeconds,
                'experience' => 0,
                'copper' => 0,
                'level_up' => false,
            ];
        }

        // 最多24小时
        $offlineSeconds = min($offlineSeconds, 86400);

        // 计算奖励：每秒 等级*1 经验，等级*0.5 铜币
        $level = $character->level;
        $experience = $level * $offlineSeconds;
        $copper = (int) ($level * $offlineSeconds * 0.5);

        // 检查是否升级
        $currentExp = $character->experience;
        $expNeeded = $character->getExperienceForNextLevel();
        $newExp = $currentExp + $experience;
        $levelUp = $newExp >= $expNeeded;

        return [
            'available' => true,
            'offline_seconds' => $offlineSeconds,
            'experience' => $experience,
            'copper' => $copper,
            'level_up' => $levelUp,
        ];
    }

    /**
     * 领取离线奖励
     */
    public function claimOfflineRewards(GameCharacter $character): array
    {
        $rewardInfo = $this->checkOfflineRewards($character);

        if (! $rewardInfo['available']) {
            return [
                'experience' => 0,
                'copper' => 0,
                'level_up' => false,
                'new_level' => $character->level,
            ];
        }

        // 更新经验
        $character->experience += $rewardInfo['experience'];
        $character->reconcileLevelFromExperience();

        // 更新铜币
        $character->copper += $rewardInfo['copper'];

        // 更新最后领取时间
        $character->claimed_offline_at = now();
        $character->save();

        return [
            'experience' => $rewardInfo['experience'],
            'copper' => $rewardInfo['copper'],
            'level_up' => $character->level > $character->getOriginal('level'),
            'new_level' => $character->level,
        ];
    }
}
