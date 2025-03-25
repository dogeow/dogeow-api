<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Word\WordRequest;
use App\Models\Word\Book;
use App\Models\Word\Word;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WordController extends Controller
{
    /**
     * 获取单词列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = Word::with('book');
        
        // 按单词书筛选
        if ($request->has('book_id')) {
            $query->where('word_book_id', $request->book_id);
        }
        
        // 按难度筛选
        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }
        
        // 按内容搜索
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                  ->orWhere('explanation', 'like', "%{$search}%");
            });
        }
        
        // 分页
        $perPage = $request->get('per_page', 20);
        $words = $query->paginate($perPage);
        
        return response()->json($words);
    }

    /**
     * 创建单词
     */
    public function store(WordRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // 检查单词是否已存在于该书中
        $exists = Word::where('word_book_id', $data['word_book_id'])
            ->where('content', $data['content'])
            ->exists();
            
        if ($exists) {
            return response()->json([
                'message' => '该单词已存在于此单词书中'
            ], 422);
        }
        
        $word = Word::create($data);
        
        // 更新单词书的总单词数
        $this->updateBookWordCount($data['word_book_id']);
        
        return response()->json([
            'message' => '单词创建成功',
            'word' => $word
        ], 201);
    }

    /**
     * 显示指定单词
     */
    public function show(Word $word): JsonResponse
    {
        $word->load('book');
        
        return response()->json($word);
    }

    /**
     * 更新指定单词
     */
    public function update(WordRequest $request, Word $word): JsonResponse
    {
        $data = $request->validated();
        
        // 如果修改了单词书，先检查新单词书中是否已有此单词
        if ($word->word_book_id != $data['word_book_id']) {
            $exists = Word::where('word_book_id', $data['word_book_id'])
                ->where('content', $data['content'])
                ->exists();
                
            if ($exists) {
                return response()->json([
                    'message' => '目标单词书中已存在该单词'
                ], 422);
            }
            
            // 记录原单词书ID，稍后更新计数
            $oldBookId = $word->word_book_id;
        }
        
        $word->update($data);
        
        // 如果修改了单词书，更新两本书的单词数
        if (isset($oldBookId)) {
            $this->updateBookWordCount($oldBookId);
            $this->updateBookWordCount($data['word_book_id']);
        }
        
        return response()->json([
            'message' => '单词更新成功',
            'word' => $word
        ]);
    }

    /**
     * 删除指定单词
     */
    public function destroy(Word $word): JsonResponse
    {
        $bookId = $word->word_book_id;
        
        $word->delete();
        
        // 更新单词书的总单词数
        $this->updateBookWordCount($bookId);
        
        return response()->json([
            'message' => '单词删除成功'
        ]);
    }
    
    /**
     * 批量添加单词
     */
    public function batchStore(Request $request): JsonResponse
    {
        $request->validate([
            'word_book_id' => 'required|exists:word_books,id',
            'words' => 'required|array',
            'words.*.content' => 'required|string|max:100',
            'words.*.explanation' => 'required|string',
        ]);
        
        $bookId = $request->word_book_id;
        $words = $request->words;
        $addedCount = 0;
        $existingWords = [];
        
        foreach ($words as $wordData) {
            // 检查单词是否已存在
            $exists = Word::where('word_book_id', $bookId)
                ->where('content', $wordData['content'])
                ->exists();
                
            if ($exists) {
                $existingWords[] = $wordData['content'];
                continue;
            }
            
            // 添加单词
            Word::create(array_merge(
                $wordData, 
                ['word_book_id' => $bookId]
            ));
            
            $addedCount++;
        }
        
        // 更新单词书的总单词数
        $this->updateBookWordCount($bookId);
        
        return response()->json([
            'message' => "已添加 {$addedCount} 个单词" . (count($existingWords) > 0 ? "，{$existingWords} 已存在" : ""),
            'added_count' => $addedCount,
            'existing_words' => $existingWords
        ]);
    }
    
    /**
     * 随机获取单词
     */
    public function random(Request $request): JsonResponse
    {
        $request->validate([
            'book_id' => 'nullable|exists:word_books,id',
            'count' => 'nullable|integer|min:1|max:50'
        ]);
        
        $count = $request->get('count', 10);
        $query = Word::with('book');
        
        if ($request->has('book_id')) {
            $query->where('word_book_id', $request->book_id);
        }
        
        $words = $query->inRandomOrder()->limit($count)->get();
        
        return response()->json($words);
    }
    
    /**
     * 更新单词书的总单词数
     */
    private function updateBookWordCount(int $bookId): void
    {
        $count = Word::where('word_book_id', $bookId)->count();
        Book::where('id', $bookId)->update(['total_words' => $count]);
    }
} 