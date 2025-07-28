<?php

namespace App\Console\Commands;

use App\Services\ChatCacheService;
use Illuminate\Console\Command;

class ClearChatCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all chat-related cache';

    protected ChatCacheService $cacheService;

    public function __construct(ChatCacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Clearing chat cache...');

        try {
            $this->cacheService->clearAllCache();
            $this->info('Chat cache cleared successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to clear cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}