<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Word\BookRequest;
use App\Models\Word\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * 获取所有单词书
     */
    public function index(Request $request): JsonResponse
    {
        $query = Book::with('category');
        
        // 按分类筛选
        if ($request->has('category_id')) {
            $query->where('word_category_id', $request->category_id);
        }
        
        // 按难度筛选
        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        
        // 默认只返回激活的
        if (!$request->has('show_all')) {
            $query->where('is_active', true);
        }
        
        // 排序
        $query->orderBy('sort_order')->orderBy('id');
        
        $books = $query->get();
        
        return response()->json($books);
    }

    /**
     * 创建单词书
     */
    public function store(BookRequest $request): JsonResponse
    {
        $book = Book::create($request->validated());

        return response()->json([
            'message' => '单词书创建成功',
            'book' => $book
        ], 201);
    }

    /**
     * 显示指定单词书，包括所有单词
     */
    public function show(Book $book): JsonResponse
    {
        $book->load(['category', 'words']);

        return response()->json($book);
    }

    /**
     * 更新指定单词书
     */
    public function update(BookRequest $request, Book $book): JsonResponse
    {
        $book->update($request->validated());

        return response()->json([
            'message' => '单词书更新成功',
            'book' => $book
        ]);
    }

    /**
     * 删除指定单词书
     */
    public function destroy(Book $book): JsonResponse
    {
        // 检查书中是否有单词
        if ($book->words()->count() > 0) {
            return response()->json([
                'message' => '该单词书中存在单词，无法删除'
            ], 422);
        }

        $book->delete();

        return response()->json([
            'message' => '单词书删除成功'
        ]);
    }
    
    /**
     * 获取书中的单词
     */
    public function words(Book $book): JsonResponse
    {
        $words = $book->words()->orderBy('id')->get();
        
        return response()->json($words);
    }
    
    /**
     * 更新单词书总单词数
     */
    public function updateTotalWords(Book $book): JsonResponse
    {
        $count = $book->words()->count();
        $book->update(['total_words' => $count]);
        
        return response()->json([
            'message' => '单词数更新成功',
            'total_words' => $count
        ]);
    }
} 