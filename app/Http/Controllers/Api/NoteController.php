<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\NoteRequest;
use App\Models\Note;
use App\Services\SlateMarkdownService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NoteController extends Controller
{
    protected $slateMarkdownService;

    public function __construct(SlateMarkdownService $slateMarkdownService)
    {
        $this->slateMarkdownService = $slateMarkdownService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $notes = Note::where('user_id', Auth::id())
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
        $contentMarkdown = '';

        if (!empty($content)) {
            $contentMarkdown = $this->slateMarkdownService->jsonToMarkdown($content);
        }

        $note = Note::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'content' => $content,
            'content_markdown' => $contentMarkdown,
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
            
            if (!empty($content)) {
                $validatedData['content_markdown'] = $this->slateMarkdownService->jsonToMarkdown($content);
            } else {
                $validatedData['content_markdown'] = '';
            }
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
