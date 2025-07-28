<?php

namespace App\Console\Commands;

use App\Services\ChatCacheService;
use Illuminate\Console\Command;

class WarmChatCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:warm-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up chat-related cache for better performance';

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
        $this->info('Warming up chat cache...');

        try {
            $this->cacheService->warmUpCache();
            $this->info('Chat cache warmed up successfully!');
            
            // Display cache statistics
            $stats = $this->cacheService->getCacheStats();
            $this->table(['Metric', 'Value'], [
                ['Cache Driver', $stats['driver']],
                ['Memory Usage', $stats['memory_usage'] ?? 'N/A'],
                ['Connected Clients', $stats['connected_clients'] ?? 'N/A'],
                ['Keyspace Hits', $stats['keyspace_hits'] ?? 'N/A'],
                ['Keyspace Misses', $stats['keyspace_misses'] ?? 'N/A'],
            ]);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to warm up cache: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}