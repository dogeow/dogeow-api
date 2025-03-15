<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\CategoryRequest;
use App\Models\Thing\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * 获取分类列表
     */
    public function index()
    {
        $categories = ItemCategory::where('user_id', Auth::id())
            ->withCount('items')
            ->get();
        
        return response()->json($categories);
    }

    /**
     * 存储新创建的分类
     */
    public function store(CategoryRequest $request)
    {
        $category = new ItemCategory($request->validated());
        $category->user_id = Auth::id();
        $category->save();
        
        return response()->json([
            'message' => '分类创建成功',
            'category' => $category
        ], 201);
    }

    /**
     * 显示指定分类
     */
    public function show(ItemCategory $category)
    {
        // 检查权限：只有分类所有者可以查看
        if ($category->user_id !== Auth::id()) {
            return response()->json(['message' => '无权查看此分类'], 403);
        }
        
        return response()->json($category->load('items'));
    }

    /**
     * 更新指定分类
     */
    public function update(CategoryRequest $request, ItemCategory $category)
    {
        // 检查权限：只有分类所有者可以更新
        if ($category->user_id !== Auth::id()) {
            return response()->json(['message' => '无权更新此分类'], 403);
        }
        
        $category->update($request->validated());
        
        return response()->json([
            'message' => '分类更新成功',
            'category' => $category
        ]);
    }

    /**
     * 删除指定分类
     */
    public function destroy(ItemCategory $category)
    {
        // 检查权限：只有分类所有者可以删除
        if ($category->user_id !== Auth::id()) {
            return response()->json(['message' => '无权删除此分类'], 403);
        }
        
        // 检查分类是否有关联的物品
        if ($category->items()->count() > 0) {
            return response()->json(['message' => '无法删除已有物品的分类'], 400);
        }
        
        $category->delete();
        
        return response()->json(['message' => '分类删除成功']);
    }
}
