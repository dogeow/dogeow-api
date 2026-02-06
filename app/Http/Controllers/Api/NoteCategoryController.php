<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\NoteCategoryRequest;
use App\Models\Note\NoteCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NoteCategoryController extends Controller
{
    /**
     * 获取所有笔记分类列表
     */
    public function index(): JsonResponse
    {
        $categories = NoteCategory::where('user_id', Auth::id())
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($categories);
    }

    /**
     * 新建笔记分类
     */
    public function store(NoteCategoryRequest $request): JsonResponse
    {
        $category = NoteCategory::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json($category, 201);
    }

    /**
     * 获取指定的笔记分类
     */
    public function show(string $id): JsonResponse
    {
        $category = NoteCategory::where('user_id', Auth::id())
            ->with('notes')
            ->findOrFail($id);

        return response()->json($category);
    }

    /**
     * 更新指定的笔记分类
     */
    public function update(NoteCategoryRequest $request, string $id): JsonResponse
    {
        $category = NoteCategory::where('user_id', Auth::id())
            ->findOrFail($id);

        $category->update($request->validated());

        return response()->json($category);
    }

    /**
     * 删除指定的笔记分类
     */
    public function destroy(string $id): JsonResponse
    {
        $category = NoteCategory::where('user_id', Auth::id())
            ->findOrFail($id);

        // 分类下的所有笔记会自动将分类设置为 null（因为外键约束为 nullOnDelete）
        $category->delete();

        return response()->json(null, 204);
    }
}
