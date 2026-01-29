<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\NoteRequest;
use App\Models\Note\Note;
use App\Jobs\TriggerKnowledgeIndexBuildJob;
use App\Models\Note\NoteLink;
use App\Models\Note\NoteTag;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $view = $request->query('view', 'list'); // 'list' or 'graph'

        if ($view === 'graph') {
            return $this->getGraph();
        }

        $notes = Note::with(['category', 'tags'])
            ->where('user_id', $this->getCurrentUserId())
            ->orderBy('updated_at', 'desc')
            ->get();

        return $this->success(['notes' => $notes], 'Notes retrieved successfully');
    }

    /**
     * 获取完整图谱数据（公开）
     */
    public function getGraph(): JsonResponse
    {
        // 获取所有 wiki 节点（is_wiki = true）和用户自己的笔记
        $nodes = Note::with('tags')
            ->where(function ($query) {
                $query->where('is_wiki', true)
                    ->orWhere('user_id', $this->getCurrentUserId());
            })->get()->map(function ($node) {
                return [
                    'id' => $node->id,
                    'title' => $node->title,
                    'slug' => $node->slug,
                    'tags' => $node->tags ? $node->tags->pluck('name')->toArray() : [],
                    'summary' => $node->summary ?? '',
                ];
            });

        $links = NoteLink::with(['sourceNote', 'targetNote'])->get()->map(function ($link) {
            // 检查关联的节点是否存在，如果不存在则跳过该链接
            if (!$link->sourceNote || !$link->targetNote) {
                return null;
            }
            return [
                'id' => $link->id,
                'source' => $link->sourceNote->id,
                'target' => $link->targetNote->id,
                'type' => $link->type,
            ];
        })->filter()->values(); // 过滤掉 null 并重新索引，保证返回数组

        return $this->success([
            'nodes' => $nodes,
            'links' => $links,
        ], 'Graph retrieved successfully');
    }

    /**
     * 通过 slug 获取文章（公开）
     */
    public function getArticleBySlug(string $slug): JsonResponse
    {
        $note = Note::where('slug', $slug)->first();

        if (!$note) {
            return $this->error('Article not found', [], 404);
        }

        return $this->success([
            'title' => $note->title,
            'slug' => $note->slug,
            'content' => $note->content,
            'content_markdown' => $note->content_markdown,
            'html' => $note->content,
        ], 'Article retrieved successfully');
    }

    /**
     * 批量获取所有 wiki 文章内容（公开）
     * 用于知识库批量加载，提高性能
     */
    public function getAllWikiArticles(): JsonResponse
    {
        try {
            $notes = Note::where('is_wiki', true)->get()->map(function ($note) {
                return [
                    'title' => $note->title,
                    'slug' => $note->slug,
                    'content' => $note->content,
                    'content_markdown' => $note->content_markdown,
                ];
            });

            return $this->success([
                'articles' => $notes,
            ], 'All wiki articles retrieved successfully');
        } catch (\Exception $e) {
            Log::error('getAllWikiArticles error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to retrieve articles: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(NoteRequest $request): JsonResponse
    {
        $data = $this->prepareNoteData($request);
        $data['user_id'] = $this->getCurrentUserId();

        $note = Note::create($data);

        // 处理标签
        $this->handleTags($request, $note);

        $note->load('tags');

        TriggerKnowledgeIndexBuildJob::dispatch();

        return $this->success(['note' => $note], 'Note created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $note = $this->findUserNote($id);
        $note->load(['category', 'tags']);

        return $this->success(['note' => $note], 'Note retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $note = $this->findUserNote($id);
        $validatedData = $this->validateUpdateData($request, $note);
        
        $note->update($validatedData);

        // 处理标签
        $this->handleTags($request, $note);

        $note->load('tags');

        TriggerKnowledgeIndexBuildJob::dispatch();

        return $this->success(['note' => $note], 'Note updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $note = $this->findUserNote($id);
        $note->delete();

        TriggerKnowledgeIndexBuildJob::dispatch();

        return $this->success([], 'Note deleted successfully');
    }

    /**
     * 查找用户的笔记或 wiki 节点
     */
    private function findUserNote(string $id): Note
    {
        // 先查找笔记（不限制条件）
        $note = Note::find($id);
        
        if (!$note) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No query results for model [App\\Models\\Note\\Note] ' . $id
            );
        }
        
        $userId = auth()->id(); // 直接使用 auth()->id()，可能返回 null
        
        // 检查权限：
        // 1. 如果是用户自己的笔记（user_id 匹配）
        // 2. 或者是 wiki 节点（is_wiki = true，允许所有认证用户编辑）
        $isUserNote = $userId !== null && $note->user_id !== null && $note->user_id === $userId;
        $isWikiNode = $note->is_wiki === true;
        
        if (!$isUserNote && !$isWikiNode) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'No query results for model [App\\Models\\Note\\Note] ' . $id
            );
        }
        
        return $note;
    }

    /**
     * 准备笔记数据
     */
    private function prepareNoteData(NoteRequest $request): array
    {
        $content = $request->content ?? '';
        $contentMarkdown = $request->content_markdown ?? '';

        // 如果提供了 JSON 格式的 content 但没有 markdown，尝试从 JSON 中提取文本作为 markdown
        if (empty($contentMarkdown) && !empty($content)) {
            // 检查是否是 JSON 格式
            $trimmedContent = trim($content);
            if (str_starts_with($trimmedContent, '{') || str_starts_with($trimmedContent, '[')) {
                try {
                    $parsedContent = json_decode($trimmedContent, true);
                    if ($parsedContent) {
                        // 从 JSON 中提取纯文本作为 markdown
                        $contentMarkdown = $this->extractTextFromEditorJson($parsedContent);
                    }
                } catch (\Exception $e) {
                    // 如果解析失败，保持为空
                    $contentMarkdown = '';
                }
            } else {
                // 如果不是 JSON 格式，直接使用 content 作为 markdown
                $contentMarkdown = $content;
            }
        }

        $data = [
            'title' => $request->title,
            'content' => $content,
            'content_markdown' => $contentMarkdown,
            'is_draft' => $request->is_draft ?? false,
        ];

        // 添加 wiki 相关字段
        if ($request->has('slug')) {
            $data['slug'] = $request->slug;
        }
        if ($request->has('summary')) {
            $data['summary'] = $request->summary;
        }
        if ($request->has('is_wiki')) {
            $data['is_wiki'] = $request->is_wiki;
        }

        // 如果没有提供 slug 且是 wiki 节点，从 title 生成
        if (($request->is_wiki ?? false) && empty($data['slug'])) {
            $data['slug'] = Note::normalizeSlug($request->title);
            $data['slug'] = Note::ensureUniqueSlug($data['slug']);
        }

        return $data;
    }

    /**
     * 从编辑器 JSON 中提取纯文本
     */
    private function extractTextFromEditorJson(array $jsonContent): string
    {
        $text = '';
        
        if (isset($jsonContent['content']) && is_array($jsonContent['content'])) {
            foreach ($jsonContent['content'] as $node) {
                $text .= $this->extractTextFromNode($node);
            }
        }
        
        return trim($text);
    }

    /**
     * 从单个节点中提取文本
     */
    private function extractTextFromNode(array $node): string
    {
        $text = '';
        
        if (isset($node['type'])) {
            if ($node['type'] === 'text' && isset($node['text'])) {
                $text .= $node['text'];
            } elseif (isset($node['content']) && is_array($node['content'])) {
                foreach ($node['content'] as $childNode) {
                    $text .= $this->extractTextFromNode($childNode);
                }
                // 在段落后添加换行
                if ($node['type'] === 'paragraph') {
                    $text .= "\n";
                }
            }
        }
        
        return $text;
    }

    /**
     * 验证更新数据
     */
    private function validateUpdateData(Request $request, ?Note $note = null): array
    {
        $validatedData = [];
        
        if ($request->has('title')) {
            $validatedData['title'] = $request->validate([
                'title' => 'required|string|max:255',
            ])['title'];
        }
        
        if ($request->has('content')) {
            $content = $request->validate([
                'content' => 'nullable|string',
            ])['content'];
            
            $validatedData['content'] = $content;
            
            // 处理 markdown 内容
            if ($request->has('content_markdown')) {
                $validatedData['content_markdown'] = $request->validate([
                    'content_markdown' => 'nullable|string',
                ])['content_markdown'];
            } else {
                // 如果没有提供 markdown，尝试从 JSON 中提取
                $contentMarkdown = '';
                if (!empty($content)) {
                    $trimmedContent = trim($content);
                    if (str_starts_with($trimmedContent, '{') || str_starts_with($trimmedContent, '[')) {
                        try {
                            $parsedContent = json_decode($trimmedContent, true);
                            if ($parsedContent) {
                                $contentMarkdown = $this->extractTextFromEditorJson($parsedContent);
                            }
                        } catch (\Exception $e) {
                            // 如果解析失败，保持为空
                            $contentMarkdown = '';
                        }
                    } else {
                        $contentMarkdown = $content;
                    }
                }
                $validatedData['content_markdown'] = $contentMarkdown;
            }
        }
        
        if ($request->has('is_draft')) {
            $validatedData['is_draft'] = $request->validate([
                'is_draft' => 'boolean',
            ])['is_draft'];
        }

        // 添加 wiki 相关字段
        if ($request->has('slug')) {
            $validatedData['slug'] = $request->validate([
                'slug' => 'nullable|string|max:255',
            ])['slug'];
        }
        if ($request->has('summary')) {
            $validatedData['summary'] = $request->validate([
                'summary' => 'nullable|string',
            ])['summary'];
        }
        if ($request->has('is_wiki')) {
            $validatedData['is_wiki'] = $request->validate([
                'is_wiki' => 'boolean',
            ])['is_wiki'];
        }

        // 如果更新了 title 但没有提供 slug，且是 wiki 节点，重新生成 slug
        if (isset($validatedData['title']) && !isset($validatedData['slug']) && $note && $note->is_wiki) {
            $validatedData['slug'] = Note::normalizeSlug($validatedData['title']);
            $validatedData['slug'] = Note::ensureUniqueSlug($validatedData['slug'], $note->id);
        }
        
        return $validatedData;
    }

    /**
     * 创建链接
     */
    public function storeLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_id' => 'required|exists:notes,id',
            'target_id' => 'required|exists:notes,id|different:source_id',
            'type' => 'nullable|string|max:255',
        ]);

        // 检查是否已存在相同的链接
        $existingLink = NoteLink::where('source_id', $validated['source_id'])
            ->where('target_id', $validated['target_id'])
            ->first();

        if ($existingLink) {
            return $this->error('Link already exists', [], 422);
        }

        $link = NoteLink::create($validated);

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
     * 删除链接
     */
    public function destroyLink(int $id): JsonResponse
    {
        $link = NoteLink::findOrFail($id);
        $link->delete();

        return $this->success([], 'Link deleted successfully');
    }

    /**
     * 处理标签关联
     * 接收标签名称数组，查找或创建标签，然后关联到笔记
     */
    private function handleTags(Request $request, Note $note): void
    {
        if (!$request->has('tags')) {
            return;
        }

        $tagNames = $request->input('tags', []);
        
        // 如果 tags 为空数组，清空所有标签关联
        if (empty($tagNames)) {
            $note->tags()->sync([]);
            return;
        }

        // 确保是数组
        if (!is_array($tagNames)) {
            return;
        }

        // 过滤空值并去重
        $tagNames = array_unique(array_filter(array_map('trim', $tagNames)));

        if (empty($tagNames)) {
            $note->tags()->sync([]);
            return;
        }

        $userId = $note->user_id ?? Auth::id();
        $tagIds = [];

        // 对于每个标签名称，查找或创建标签
        foreach ($tagNames as $tagName) {
            if (empty($tagName)) {
                continue;
            }

            // 查找或创建标签
            // 注意：wiki 节点可能没有 user_id，这种情况下使用当前登录用户
            /** @var NoteTag $tag */
            $tag = NoteTag::firstOrCreate(
                [
                    'name' => $tagName,
                    'user_id' => $userId,
                ],
                [
                    'color' => '#3b82f6', // 默认蓝色
                ]
            );

            $tagIds[] = $tag->id;
        }

        // 同步标签关联
        $note->tags()->sync($tagIds);
    }
}
