<?php

namespace App\Console\Commands;

use App\Models\Game\GameItemDefinition;
use Illuminate\Console\Command;

class SyncPotions extends Command
{
    protected $signature = 'potions:sync';
    protected $description = '同步药品定义到数据库（暗黑2风格）';

    public function handle(): void
    {
        $this->info('开始同步药品定义到数据库...');

        // 定义药品配置（暗黑2风格）
        $potions = [
            'hp' => [
                'minor' => ['name' => '轻型生命药水', 'restore' => 50, 'desc' => '恢复50点生命值'],
                'light' => ['name' => '生命药水', 'restore' => 100, 'desc' => '恢复100点生命值'],
                'medium' => ['name' => '重型生命药水', 'restore' => 200, 'desc' => '恢复200点生命值'],
                'full' => ['name' => '超重型生命药水', 'restore' => 400, 'desc' => '恢复400点生命值'],
            ],
            'mp' => [
                'minor' => ['name' => '轻型法力药水', 'restore' => 30, 'desc' => '恢复30点法力值'],
                'light' => ['name' => '法力药水', 'restore' => 60, 'desc' => '恢复60点法力值'],
                'medium' => ['name' => '重型法力药水', 'restore' => 120, 'desc' => '恢复120点法力值'],
                'full' => ['name' => '超重型法力药水', 'restore' => 240, 'desc' => '恢复240点法力值'],
            ],
        ];

        $created = 0;
        $skipped = 0;
        $deleted = 0;

        // 删除旧的药品定义（没有 gem_stats 的）
        $oldPotions = GameItemDefinition::query()
            ->where('type', 'potion')
            ->where(function ($query) {
                $query->whereNull('gem_stats')->orWhereJsonLength('gem_stats', 0);
            })
            ->get();

        foreach ($oldPotions as $potion) {
            $potion->delete();
            $deleted++;
            $this->warn("删除旧药品: {$potion->name}");
        }

        // 创建新的药品定义
        foreach ($potions as $type => $levels) {
            $statKey = $type === 'hp' ? 'max_hp' : 'max_mana';

            foreach ($levels as $level => $config) {
                // 检查是否已存在
                $existing = GameItemDefinition::query()
                    ->where('type', 'potion')
                    ->where('sub_type', $type)
                    ->whereJsonContains('gem_stats->restore', $config['restore'])
                    ->first();

                if ($existing) {
                    $skipped++;
                    $this->line("✓ {$config['name']} 已存在，跳过");
                    continue;
                }

                // 创建新药品定义
                GameItemDefinition::create([
                    'name' => $config['name'],
                    'type' => 'potion',
                    'sub_type' => $type,
                    'base_stats' => [$statKey => $config['restore']],
                    'required_level' => 1,
                    'required_strength' => 0,
                    'required_dexterity' => 0,
                    'required_energy' => 0,
                    'icon' => 'potion',
                    'description' => $config['desc'],
                    'is_active' => true,
                    'sockets' => 0,
                    'gem_stats' => ['restore' => $config['restore']],
                ]);

                $created++;
                $this->info("创建: {$config['name']} (恢复 {$config['restore']} 点" . ($type === 'hp' ? '生命' : '法力') . ')');
            }
        }

        $this->newLine();
        $this->info('药品定义同步完成！');
        $this->info("删除: {$deleted} 条旧记录");
        $this->info("创建: {$created} 条新记录");
        $this->info("跳过: {$skipped} 条已存在记录");
        $this->info("共计 " . count($potions['hp']) . " 种生命药水和 " . count($potions['mp']) . " 种法力药水");
    }
}
