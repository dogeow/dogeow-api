<?php

namespace App\Console\Commands;

use App\Models\Note;
use App\Services\SlateMarkdownService;
use Illuminate\Console\Command;

class UpdateNotesMarkdown extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notes:update-markdown {--limit=100 : 每批处理的记录数}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '为所有现有笔记生成Markdown内容';

    /**
     * 执行命令
     */
    public function handle(SlateMarkdownService $slateMarkdownService): int
    {
        $limit = (int) $this->option('limit');
        $this->info('开始更新笔记Markdown内容...');
        
        $total = Note::whereNull('content_markdown')->orWhere('content_markdown', '')->count();
        
        if ($total === 0) {
            $this->info('没有需要更新的笔记');
            return Command::SUCCESS;
        }
        
        $this->info("发现 {$total} 个需要更新的笔记");
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        $processed = 0;
        
        // 分批处理，避免内存问题
        Note::whereNull('content_markdown')
            ->orWhere('content_markdown', '')
            ->orderBy('id')
            ->chunk($limit, function ($notes) use ($slateMarkdownService, $bar, &$processed) {
                foreach ($notes as $note) {
                    // 仅当有内容时才处理
                    if (!empty($note->content)) {
                        $markdownContent = $slateMarkdownService->jsonToMarkdown($note->content);
                        $note->content_markdown = $markdownContent;
                        $note->save();
                    } else {
                        // 如果没有内容，设置为空字符串
                        $note->content_markdown = '';
                        $note->save();
                    }
                    
                    $bar->advance();
                    $processed++;
                }
            });
        
        $bar->finish();
        $this->newLine();
        $this->info("完成！已更新 {$processed} 个笔记的Markdown内容");
        
        return Command::SUCCESS;
    }
}
