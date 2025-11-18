<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wiki\WikiLink;
use App\Models\Wiki\WikiNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WikiController extends Controller
{
    /**
     * 从标题生成 slug
     */
    private function normalizeSlug(string $title): string
    {
        // 转换为小写
        $slug = mb_strtolower($title, 'UTF-8');
        
        // 替换空格为连字符
        $slug = preg_replace('/\s+/', '-', $slug);
        
        // 移除特殊字符，保留中文、字母、数字、连字符
        $slug = preg_replace('/[^\w\s\x{4e00}-\x{9fa5}-]/u', '', $slug);
        
        // 合并多个连字符
        $slug = preg_replace('/-+/', '-', $slug);
        
        // 去除首尾连字符
        $slug = trim($slug, '-');
        
        // 如果为空，使用原始标题
        return $slug ?: $title;
    }

    /**
     * 确保 slug 唯一
     */
    private function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (WikiNode::where('slug', $slug)
            ->when($excludeId, fn($query) => $query->where('id', '!=', $excludeId))
            ->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * 获取完整图谱数据（公开）
     */
    public function index(): JsonResponse
    {
        $nodes = WikiNode::all()->map(function ($node) {
            return [
                'id' => $node->id,
                'title' => $node->title,
                'slug' => $node->slug,
                'tags' => $node->tags ?? [],
                'summary' => $node->summary ?? '',
            ];
        });

        $links = WikiLink::with(['sourceNode', 'targetNode'])->get()->map(function ($link) {
            return [
                'id' => $link->id,
                'source' => $link->sourceNode->id,
                'target' => $link->targetNode->id,
                'type' => $link->type,
            ];
        });

        return $this->success([
            'nodes' => $nodes,
            'links' => $links,
        ], 'Wiki graph retrieved successfully');
    }

    /**
     * 获取文章内容（公开）
     */
    public function getArticle(string $slug): JsonResponse
    {
        $node = WikiNode::where('slug', $slug)->first();

        if (!$node) {
            return $this->error('Article not found', [], 404);
        }

        return $this->success([
            'title' => $node->title,
            'slug' => $node->slug,
            'content' => $node->content,
            'content_markdown' => $node->content_markdown,
            'html' => $node->content, // 如果需要 HTML，可以在这里转换
        ], 'Article retrieved successfully');
    }

    /**
     * 创建节点（管理员）
     */
    public function storeNode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:wiki_nodes,slug',
            'tags' => 'nullable|array',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'content_markdown' => 'nullable|string',
        ]);

        // 如果没有提供 slug，从 title 生成
        if (empty($validated['slug'])) {
            $validated['slug'] = $this->normalizeSlug($validated['title']);
        }

        // 确保 slug 唯一
        $validated['slug'] = $this->ensureUniqueSlug($validated['slug']);

        $node = WikiNode::create($validated);

        return $this->success([
            'node' => [
                'id' => $node->id,
                'title' => $node->title,
                'slug' => $node->slug,
                'tags' => $node->tags ?? [],
                'summary' => $node->summary ?? '',
            ],
        ], 'Node created successfully', 201);
    }

    /**
     * 更新节点（管理员）
     */
    public function updateNode(Request $request, int $id): JsonResponse
    {
        $node = WikiNode::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:wiki_nodes,slug,' . $id,
            'tags' => 'nullable|array',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',
            'content_markdown' => 'nullable|string',
        ]);

        // 如果更新了 title 但没有提供 slug，重新生成 slug
        if (isset($validated['title']) && !isset($validated['slug'])) {
            $validated['slug'] = $this->normalizeSlug($validated['title']);
        }

        // 如果提供了 slug，确保唯一
        if (isset($validated['slug'])) {
            $validated['slug'] = $this->ensureUniqueSlug($validated['slug'], $id);
        }

        $node->update($validated);

        return $this->success([
            'node' => [
                'id' => $node->id,
                'title' => $node->title,
                'slug' => $node->slug,
                'tags' => $node->tags ?? [],
                'summary' => $node->summary ?? '',
            ],
        ], 'Node updated successfully');
    }

    /**
     * 删除节点（管理员，级联删除相关链接）
     */
    public function destroyNode(int $id): JsonResponse
    {
        $node = WikiNode::findOrFail($id);
        $node->delete(); // 级联删除由数据库外键约束处理

        return $this->success([], 'Node deleted successfully');
    }

    /**
     * 创建链接（管理员）
     */
    public function storeLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => 'required|exists:wiki_nodes,id',
            'target_id' => 'required|exists:wiki_nodes,id|different:source_id',
            'type' => 'nullable|string|max:255',
        ]);

        // 检查是否已存在相同的链接
        $existingLink = WikiLink::where('source_id', $validated['source_id'])
            ->where('target_id', $validated['target_id'])
            ->first();

        if ($existingLink) {
            return $this->error('Link already exists', [], 422);
        }

        $link = WikiLink::create($validated);

        return $this->success([
            'link' => [
                'id' => $link->id,
                'source' => $link->source_id,
                'target' => $link->target_id,
                'type' => $link->type,
            ],
        ], 'Link created successfully', 201);
    }

    /**
     * 删除链接（管理员）
     */
    public function destroyLink(int $id): JsonResponse
    {
        $link = WikiLink::findOrFail($id);
        $link->delete();

        return $this->success([], 'Link deleted successfully');
    }
}
