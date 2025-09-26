<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\NoteRequest;
use App\Models\Note\Note;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $notes = Note::with(['category', 'tags'])
            ->where('user_id', $this->getCurrentUserId())
            ->orderBy('updated_at', 'desc')
            ->get();

        return $this->success(['notes' => $notes], 'Notes retrieved successfully');
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

        return [
            'title' => $request->title,
            'content' => $content,
            'content_markdown' => $contentMarkdown,
            'is_draft' => $request->is_draft ?? false,
        ];
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
        
        return $validatedData;
    }
}
