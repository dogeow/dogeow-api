<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Word\CategoryRequest;
use App\Models\Word\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * 获取所有单词分类
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('sort_order')
            ->where('is_active', true)
            ->get();

        return response()->json($categories);
    }

    /**
     * 获取所有单词分类，包括书籍数量（管理员）
     */
    public function all(): JsonResponse
    {
        $categories = Category::withCount('books')
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }

    /**
     * 创建单词分类
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return response()->json([
            'message' => '分类创建成功',
            'category' => $category
        ], 201);
    }

    /**
     * 显示指定单词分类
     */
    public function show(Category $category): JsonResponse
    {
        $category->load('books');

        return response()->json($category);
    }

    /**
     * 更新指定单词分类
     */
    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return response()->json([
            'message' => '分类更新成功',
            'category' => $category
        ]);
    }

    /**
     * 删除指定单词分类
     */
    public function destroy(Category $category): JsonResponse
    {
        // 检查分类下是否有单词书
        if ($category->books()->count() > 0) {
            return response()->json([
                'message' => '该分类下存在单词书，无法删除'
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => '分类删除成功'
        ]);
    }
} 