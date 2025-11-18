<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Wiki\WikiNode;
use App\Models\Wiki\WikiLink;
use Illuminate\Support\Str;

class WikiGraphSeeder extends Seeder
{
    /**
     * 从标题生成 slug
     */
    private function normalizeSlug(string $title): string
    {
        $slug = mb_strtolower($title, 'UTF-8');
        $slug = preg_replace('/\s+/', '-', $slug);
        // 移除特殊字符，保留中文、字母、数字、连字符
        // 使用 Unicode 属性匹配中文字符
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: $title;
    }

    /**
     * 确保 slug 唯一
     */
    private function ensureUniqueSlug(string $slug, array $existingSlugs): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (in_array($slug, $existingSlugs)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 读取 JSON 文件
        $jsonPath = base_path('../dogeow/data/wiki/graph.json');
        
        if (!file_exists($jsonPath)) {
            $this->command->warn("JSON file not found: {$jsonPath}");
            return;
        }

        $jsonContent = file_get_contents($jsonPath);
        $data = json_decode($jsonContent, true);

        if (!$data || !isset($data['nodes'])) {
            $this->command->error('Invalid JSON data');
            return;
        }

        // 清空现有数据（可选）
        if ($this->command->confirm('Do you want to clear existing wiki data?', false)) {
            WikiLink::truncate();
            WikiNode::truncate();
            $this->command->info('Existing data cleared.');
        }

        // 创建节点
        $nodeMap = [];
        $existingSlugs = [];
        
        foreach ($data['nodes'] as $nodeData) {
            $title = $nodeData['title'] ?? '';
            $slug = $nodeData['slug'] ?? $this->normalizeSlug($title);
            $slug = $this->ensureUniqueSlug($slug, $existingSlugs);
            $existingSlugs[] = $slug;

            $node = WikiNode::create([
                'title' => $title,
                'slug' => $slug,
                'tags' => $nodeData['tags'] ?? [],
                'summary' => $nodeData['summary'] ?? null,
                'content' => null,
                'content_markdown' => null,
            ]);

            // 使用原始 title 或 id 作为 key，用于后续链接映射
            $key = $nodeData['id'] ?? $nodeData['title'] ?? $node->id;
            $nodeMap[$key] = $node->id;
        }

        $this->command->info("Created " . count($nodeMap) . " nodes.");

        // 创建链接
        $linkCount = 0;
        if (isset($data['links'])) {
            foreach ($data['links'] as $linkData) {
                $sourceKey = $linkData['source'] ?? null;
                $targetKey = $linkData['target'] ?? null;

                if (!$sourceKey || !$targetKey) {
                    continue;
                }

                $sourceId = $nodeMap[$sourceKey] ?? null;
                $targetId = $nodeMap[$targetKey] ?? null;

                if (!$sourceId || !$targetId) {
                    $this->command->warn("Skipping link: source or target not found");
                    continue;
                }

                // 检查是否已存在
                $exists = WikiLink::where('source_id', $sourceId)
                    ->where('target_id', $targetId)
                    ->exists();

                if (!$exists) {
                    WikiLink::create([
                        'source_id' => $sourceId,
                        'target_id' => $targetId,
                        'type' => $linkData['type'] ?? null,
                    ]);
                    $linkCount++;
                }
            }
        }

        $this->command->info("Created {$linkCount} links.");
        $this->command->info('Wiki graph data imported successfully!');
    }
}
