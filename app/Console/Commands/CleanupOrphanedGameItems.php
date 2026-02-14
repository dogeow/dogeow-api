<?php

namespace App\Console\Commands;

use App\Models\Game\GameItem;
use Illuminate\Console\Command;

class CleanupOrphanedGameItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'game:cleanup-orphaned-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清理数据库中孤立的物品记录（没有对应 definition 的物品）';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('开始清理孤立的物品记录...');

        // 查找所有没有对应 definition 的物品
        $orphanedItems = GameItem::query()
            ->whereNotIn('definition_id', function ($query) {
                $query->select('id')
                    ->from('game_item_definitions');
            })
            ->get();

        $count = $orphanedItems->count();

        if ($count === 0) {
            $this->info('没有找到孤立的物品记录。');
            return self::SUCCESS;
        }

        $this->warn("找到 {$count} 个孤立的物品记录。");

        if ($this->confirm('是否删除这些记录？')) {
            $deleted = GameItem::query()
                ->whereNotIn('definition_id', function ($query) {
                    $query->select('id')
                        ->from('game_item_definitions');
                })
                ->delete();

            $this->info("成功删除 {$deleted} 个孤立的物品记录。");
            return self::SUCCESS;
        }

        $this->info('已取消操作。');
        return self::SUCCESS;
    }
}
