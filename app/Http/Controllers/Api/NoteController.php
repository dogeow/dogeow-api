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
            ->where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($notes);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(NoteRequest $request): JsonResponse
    {
        $content = $request->content ?? '';
        $contentMarkdown = $request->content_markdown ?? '';

        // 如果前端没有提供 markdown，使用 content 作为 markdown
        if (empty($contentMarkdown) && !empty($content)) {
            $contentMarkdown = $content;
        }

        $note = Note::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'content' => $content,
            'content_markdown' => $contentMarkdown,
            'is_draft' => $request->is_draft ?? false,
        ]);

        return response()->json($note, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $note = Note::where('user_id', Auth::id())
            ->findOrFail($id);
        $note->load(['category', 'tags']);

        return response()->json($note);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $note = Note::where('user_id', Auth::id())
            ->findOrFail($id);

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
            
            // 检查是否有前端提供的 markdown
            if ($request->has('content_markdown')) {
                $validatedData['content_markdown'] = $request->validate([
                    'content_markdown' => 'nullable|string',
                ])['content_markdown'];
            } else if (!empty($content)) {
                // 如果没有提供 markdown，使用 content 作为 markdown
                $validatedData['content_markdown'] = $content;
            } else {
                $validatedData['content_markdown'] = '';
            }
        }
        
        if ($request->has('is_draft')) {
            $validatedData['is_draft'] = $request->validate([
                'is_draft' => 'boolean',
            ])['is_draft'];
        }
        
        $note->update($validatedData);

        return response()->json($note);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $note = Note::where('user_id', Auth::id())
            ->findOrFail($id);
            
        $note->delete();

        return response()->json(null, 204);
    }
}
