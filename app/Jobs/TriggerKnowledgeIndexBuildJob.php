<?php

namespace App\Jobs;

use App\Events\KnowledgeIndexUpdated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerKnowledgeIndexBuildJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    /** 唯一锁持有时间（秒），避免重复入队 */
    public int $uniqueFor = 120;

    /**
     * 创建任务实例
     */
    public function __construct()
    {
        $this->onQueue('default');
    }

    /**
     * 执行任务：请求 Next 知识库构建接口，成功后广播更新时间
     */
    public function handle(): void
    {
        $url = config('services.knowledge.build_index_url');
        if (empty($url)) {
            Log::warning('KnowledgeIndexBuild: KNOWLEDGE_BUILD_INDEX_URL 未配置，跳过构建');
            return;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->post($url, ['force' => true]);

            if ($response->successful()) {
                $updatedAt = now()->toIso8601String();
                broadcast(new KnowledgeIndexUpdated($updatedAt));
                Log::info('KnowledgeIndexBuild: 知识库索引已触发更新', [
                    'url' => $url,
                    'updated_at' => $updatedAt,
                ]);
            } else {
                Log::warning('KnowledgeIndexBuild: 构建接口返回非成功', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('KnowledgeIndexBuild: 请求构建接口失败', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return 'knowledge-index-build';
    }
}
