<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\NoteRequest;
use App\Models\Note\Note;
use App\Models\Note\NoteLink;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
            return [
                'id' => $link->id,
                'source' => $link->sourceNote->id,
                'target' => $link->targetNote->id,
                'type' => $link->type,
            ];
        });

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
     * Store a newly created resource in storage.
     */
    public function store(NoteRequest $request): JsonResponse
    {
        $data = $this->prepareNoteData($request);
        $data['user_id'] = $this->getCurrentUserId();

        $note = Note::create($data);

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
        $validatedData = $this->validateUpdateData($request);
        
        $note->update($validatedData);

        return $this->success(['note' => $note], 'Note updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $note = $this->findUserNote($id);
        $note->delete();

        return $this->success([], 'Note deleted successfully');
    }

    /**
     * 查找用户的笔记
     */
    private function findUserNote(string $id): Note
    {
        return Note::where('user_id', $this->getCurrentUserId())->findOrFail($id);
    }

    /**
     * 准备笔记数据
     */
    private function prepareNoteData(NoteRequest $request): array
    {
        $content = $request->content ?? '';
        $contentMarkdown = $request->content_markdown ?? '';

        // 如果前端没有提供 markdown，使用 content 作为 markdown
        if (empty($contentMarkdown) && !empty($content)) {
            $contentMarkdown = $content;
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
     * 验证更新数据
     */
    private function validateUpdateData(Request $request): array
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
                $validatedData['content_markdown'] = $content ?: '';
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
        if (isset($validatedData['title']) && !isset($validatedData['slug'])) {
            $noteId = $request->route('id');
            $note = Note::findOrFail($noteId);
            if ($note->is_wiki) {
                $validatedData['slug'] = Note::normalizeSlug($validatedData['title']);
                $validatedData['slug'] = Note::ensureUniqueSlug($validatedData['slug'], $note->id);
            }
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
}
