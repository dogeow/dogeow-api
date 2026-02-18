<?php

namespace App\Services\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameMonsterDefinition;
use Illuminate\Support\Facades\Log;

/**
 * 单回合战斗处理器：技能选择、目标选择、伤害计算、反击、奖励结算
 */
class CombatRoundProcessor
{
    /**
     * 处理一回合战斗（支持多怪物）
     *
     * @return array{round_damage_dealt: int, round_damage_taken: int, new_monster_hp: int, new_char_hp: int, new_char_mana: int, defeat: bool, has_alive_monster: bool, skills_used_this_round: array, new_cooldowns: array, new_skills_aggregated: array, monsters_updated: array, experience_gained: int, copper_gained: int}
     */
    public function processOneRound(
        GameCharacter $character,
        int $currentRound,
        array $skillCooldowns,
        array $skillsUsedAggregated,
        array $requestedSkillIds = []
    ): array {
        $character->initializeHpMana();

        $charStats = $character->getCombatStats();
        $charHp = $character->getCurrentHp();
        $currentMana = $character->getCurrentMana();
        $charAttack = $charStats['attack'];
        $charDefense = $charStats['defense'];
        $charCritRate = $charStats['crit_rate'];
        $charCritDamage = $charStats['crit_damage'];

        $monsters = $character->combat_monsters ?? [];
        $difficulty = $character->getDifficultyMultipliers();

        $hpAtRoundStart = [];
        foreach ($monsters as $idx => $m) {
            $hpAtRoundStart[$idx] = is_array($m) ? ($m['hp'] ?? 0) : 0;
        }

        $skillResult = $this->resolveRoundSkill(
            $character,
            $requestedSkillIds,
            $currentRound,
            $currentMana,
            $skillCooldowns
        );
        $currentMana = $skillResult['mana'];
        $isAoeSkill = $skillResult['is_aoe'];
        $skillDamage = $skillResult['skill_damage'];
        $skillsUsedThisRound = $skillResult['skills_used_this_round'];
        $newCooldowns = $skillResult['new_cooldowns'];

        $isCrit = (rand(1, 100) / 100) <= $charCritRate;
        $targetMonsters = $this->selectRoundTargets($monsters, $isAoeSkill);
        $useAoe = $isAoeSkill && ! empty($targetMonsters);

        // 收集技能命中的目标位置
        $skillTargetPositions = array_map(fn ($m) => $m['position'] ?? null, $targetMonsters);
        $skillTargetPositions = array_filter($skillTargetPositions, fn ($p) => $p !== null);

        [$monstersUpdated, $totalDamageDealt] = $this->applyCharacterDamageToMonsters(
            $monsters,
            $targetMonsters,
            $charAttack,
            $skillDamage,
            $isCrit,
            $charCritDamage,
            $useAoe
        );

        $totalMonsterDamage = $this->calculateMonsterCounterDamage($monstersUpdated, $charDefense);
        $charHp -= $totalMonsterDamage;

        $character->combat_monsters = $monstersUpdated;
        $newTotalHp = array_sum(array_column(array_filter($monstersUpdated, 'is_array'), 'hp'));

        $newSkillsAggregated = $this->aggregateSkillsUsed($skillsUsedThisRound, $skillsUsedAggregated);
        $hasAliveMonster = $this->hasAliveMonster($monstersUpdated);

        [$totalExperience, $totalCopper] = $this->calculateRoundDeathRewards(
            $monstersUpdated,
            $hpAtRoundStart,
            $difficulty
        );

        return [
            'round_damage_dealt' => $totalDamageDealt,
            'round_damage_taken' => $totalMonsterDamage,
            'new_monster_hp' => $newTotalHp,
            'new_char_hp' => $charHp,
            'new_char_mana' => $currentMana,
            'defeat' => $charHp <= 0,
            'has_alive_monster' => $hasAliveMonster,
            'skills_used_this_round' => $skillsUsedThisRound,
            'skill_target_positions' => array_values($skillTargetPositions),
            'new_cooldowns' => $newCooldowns,
            'new_skills_aggregated' => $newSkillsAggregated,
            'monsters_updated' => $monstersUpdated,
            'experience_gained' => $totalExperience,
            'copper_gained' => $totalCopper,
        ];
    }

    /**
     * 解析本回合使用的技能（蓝量、冷却、单体/群体）
     *
     * @return array{mana: int, is_aoe: bool, skill_damage: int, skills_used_this_round: array, new_cooldowns: array}
     */
    private function resolveRoundSkill(
        GameCharacter $character,
        array $requestedSkillIds,
        int $currentRound,
        int $currentMana,
        array $skillCooldowns
    ): array {
        $isAoeSkill = false;
        $skillDamage = 0;
        $skillsUsedThisRound = [];
        $newCooldowns = $skillCooldowns;

        $activeSkills = $character->skills()
            ->whereHas('skill', fn ($q) => $q->where('type', 'active'))
            ->with('skill')
            ->get();

        foreach ($requestedSkillIds as $rid) {
            foreach ($activeSkills as $charSkill) {
                if ($charSkill->skill_id !== $rid) {
                    continue;
                }
                $skill = $charSkill->skill;
                $cooldownEnd = $newCooldowns[$rid] ?? 0;
                if ($currentMana >= $skill->mana_cost && $cooldownEnd <= $currentRound) {
                    $skillDamage = (int) $skill->damage;
                    $currentMana -= $skill->mana_cost;
                    $newCooldowns[$rid] = $currentRound + (int) $skill->cooldown;
                    $skillsUsedThisRound[] = [
                        'skill_id' => $skill->id,
                        'name' => $skill->name,
                        'icon' => $skill->icon,
                        'target_type' => $skill->target_type ?? 'single',
                    ];
                    $isAoeSkill = ($skill->target_type ?? 'single') === 'all';

                    return [
                        'mana' => $currentMana,
                        'is_aoe' => $isAoeSkill,
                        'skill_damage' => $skillDamage,
                        'skills_used_this_round' => $skillsUsedThisRound,
                        'new_cooldowns' => $newCooldowns,
                    ];
                }
                break;
            }
        }

        return [
            'mana' => $currentMana,
            'is_aoe' => $isAoeSkill,
            'skill_damage' => $skillDamage,
            'skills_used_this_round' => $skillsUsedThisRound,
            'new_cooldowns' => $newCooldowns,
        ];
    }

    /**
     * 选择本回合攻击目标（AOE 为全部存活，单体为随机一只）
     *
     * @param  array<int, array<string, mixed>>  $monsters
     * @return array<int, array<string, mixed>>
     */
    private function selectRoundTargets(array $monsters, bool $isAoeSkill): array
    {
        $aliveMonsters = array_filter($monsters, fn ($m) => is_array($m) && ($m['hp'] ?? 0) > 0);
        if (empty($aliveMonsters)) {
            return [];
        }
        $aliveValues = array_values($aliveMonsters);
        if ($isAoeSkill) {
            return $aliveValues;
        }
        $randomIndex = array_rand($aliveValues);

        return [$aliveValues[$randomIndex]];
    }

    /**
     * 对目标怪物施加角色伤害，返回更新后的怪物列表与总伤害
     *
     * @param  array<int, array<string, mixed>>  $monsters
     * @param  array<int, array<string, mixed>>  $targetMonsters
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function applyCharacterDamageToMonsters(
        array $monsters,
        array $targetMonsters,
        int $charAttack,
        int $skillDamage,
        bool $isCrit,
        float $charCritDamage,
        bool $useAoe
    ): array {
        $totalDamageDealt = 0;
        $monstersUpdated = [];

        foreach ($monsters as $idx => $m) {
            if (! is_array($m)) {
                $monstersUpdated[$idx] = null;

                continue;
            }
            $m['damage_taken'] = 0;

            // 新出现的怪物不受攻击（下一轮才能攻击）
            if (isset($m['is_new']) && $m['is_new'] === true) {
                Log::info('Skipping new monster attack', ['monster' => $m['name'], 'is_new' => true]);
                $monstersUpdated[$idx] = $m;

                continue;
            }

            if (($m['hp'] ?? 0) <= 0) {
                $monstersUpdated[$idx] = $m;

                continue;
            }

            $isTarget = $this->isMonsterInTargets($m, $targetMonsters);
            if (! $isTarget) {
                $monstersUpdated[$idx] = $m;

                continue;
            }

            $mDefense = (int) ($m['defense'] ?? 0);
            $defenseReduction = config('game.combat.defense_reduction', 0.5);
            $baseDamage = $charAttack - $mDefense * $defenseReduction;
            $damage = $skillDamage > 0
                ? (int) ($baseDamage + $skillDamage)
                : (int) ($baseDamage * ($isCrit ? $charCritDamage : 1));
            $aoeMultiplier = config('game.combat.aoe_damage_multiplier', 0.7);
            $targetDamage = $useAoe ? (int) ($damage * $aoeMultiplier) : $damage;

            $m['hp'] = max(0, ($m['hp'] ?? 0) - $targetDamage);
            $m['damage_taken'] = $targetDamage;
            $totalDamageDealt += $targetDamage;
            $monstersUpdated[$idx] = $m;
        }

        // 清除所有新怪物标记 现在可以攻击了
        foreach ($monstersUpdated as $idx => $m) {
            if (is_array($m) && isset($m['is_new'])) {
                unset($monstersUpdated[$idx]['is_new']);
            }
        }

        return [$monstersUpdated, $totalDamageDealt];
    }

    /**
     * 按槽位 position 判断是否为攻击目标（同种同等级多只怪物时只命中选中的那一只）
     *
     * @param  array<string, mixed>  $monster
     * @param  array<int, array<string, mixed>>  $targets
     */
    private function isMonsterInTargets(array $monster, array $targets): bool
    {
        $slot = $monster['position'] ?? null;
        if ($slot === null) {
            return false;
        }
        foreach ($targets as $tm) {
            if (($tm['position'] ?? null) === $slot) {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算所有存活怪物对角色造成的总反击伤害
     *
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     */
    private function calculateMonsterCounterDamage(array $monstersUpdated, int $charDefense): int
    {
        $total = 0;
        foreach ($monstersUpdated as $m) {
            if (! is_array($m) || ($m['hp'] ?? 0) <= 0) {
                continue;
            }
            $monsterAttack = $m['attack'] ?? 0;
            $monsterDefenseReduction = config('game.combat.monster_defense_reduction', 0.3);
            $monsterDamage = $monsterAttack - $charDefense * $monsterDefenseReduction;
            if ($monsterDamage > 0) {
                $total += (int) $monsterDamage;
            }
        }

        return $total;
    }

    /**
     * 将本回合技能使用合并到累计统计
     *
     * @param  array<int, array{skill_id: int, name: string, icon: string|null}>  $skillsUsedThisRound
     * @param  array<int|string, array{skill_id: int, name: string, icon: string|null, use_count: int}>  $skillsUsedAggregated
     * @return array<int, array{skill_id: int, name: string, icon: string|null, use_count: int}>
     */
    private function aggregateSkillsUsed(array $skillsUsedThisRound, array $skillsUsedAggregated): array
    {
        $aggregated = $skillsUsedAggregated;
        foreach ($skillsUsedThisRound as $entry) {
            $id = $entry['skill_id'];
            if (! isset($aggregated[$id])) {
                $aggregated[$id] = [
                    'skill_id' => $entry['skill_id'],
                    'name' => $entry['name'],
                    'icon' => $entry['icon'] ?? null,
                    'use_count' => 0,
                ];
            }
            $aggregated[$id]['use_count']++;
        }

        return array_values($aggregated);
    }

    /**
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     */
    private function hasAliveMonster(array $monstersUpdated): bool
    {
        foreach ($monstersUpdated as $m) {
            if (is_array($m) && ($m['hp'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * 计算本回合死亡怪物的经验与铜币奖励（已乘难度系数）
     *
     * @param  array<int, array<string, mixed>>  $monstersUpdated
     * @param  array<int, int>  $hpAtRoundStart
     * @return array{0: int, 1: int}
     */
    private function calculateRoundDeathRewards(
        array $monstersUpdated,
        array $hpAtRoundStart,
        array $difficulty
    ): array {
        $totalExperience = 0;
        $totalCopper = 0;
        $rewardMultiplier = $difficulty['reward'] ?? 1;

        foreach ($monstersUpdated as $i => $monster) {
            if (! is_array($monster)) {
                continue;
            }
            $before = $hpAtRoundStart[$i] ?? 0;
            $after = $monster['hp'] ?? 0;
            if ($before > 0 && $after <= 0) {
                $totalExperience += $monster['experience'] ?? 0;

                // 使用怪物定义的 drop_table 配置计算铜币掉落
                $copperGained = $this->calculateMonsterCopperLoot($monster);
                $totalCopper += $copperGained;
            }
        }

        return [
            (int) ($totalExperience * $rewardMultiplier),
            (int) ($totalCopper * $rewardMultiplier),
        ];
    }

    /**
     * 根据怪物定义计算铜币掉落
     */
    private function calculateMonsterCopperLoot(array $monster): int
    {
        $monsterId = $monster['id'] ?? null;
        if (! $monsterId) {
            return rand(1, 10); // 回退到随机铜币
        }

        $definition = GameMonsterDefinition::query()->find($monsterId);
        if (! $definition) {
            return rand(1, 10); // 回退到随机铜币
        }

        $dropTable = $definition->drop_table ?? [];
        $level = $monster['level'] ?? $definition->level;

        // 优先使用怪物 drop_table 的配置，否则用全局配置
        $copperConfig = config('game.copper_drop');
        if (! empty($dropTable['copper_chance'])) {
            $copperChance = $dropTable['copper_chance'];
            $base = (int) ($dropTable['copper_base'] ?? $copperConfig['base']);
            $range = (int) ($dropTable['copper_range'] ?? $copperConfig['range']);
        } else {
            $copperChance = $copperConfig['chance'];
            $base = $copperConfig['base'];
            $range = $copperConfig['range'];
        }

        if (! $this->rollChanceForProcessor($copperChance)) {
            return 0;
        }

        return random_int($base, $base + $range);
    }

    /**
     * 概率判定（复制自 GameMonsterDefinition）
     */
    private function rollChanceForProcessor(float $chance): bool
    {
        // $chance是0~1，例如0.12就是12%概率
        return mt_rand() / mt_getrandmax() < $chance;
    }
}
