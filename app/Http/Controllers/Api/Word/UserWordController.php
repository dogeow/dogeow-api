<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Word\UserWordRequest;
use App\Models\Word\UserWord;
use App\Models\Word\Word;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserWordController extends Controller
{
    /**
     * 获取用户的单词学习记录
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $query = UserWord::with(['word', 'book'])
            ->where('user_id', $userId);
            
        // 按单词书筛选
        if ($request->has('book_id')) {
            $query->where('word_book_id', $request->book_id);
        }
        
        // 按学习状态筛选
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // 按收藏状态筛选
        if ($request->has('is_favorite')) {
            $query->where('is_favorite', $request->is_favorite);
        }
        
        // 按复习时间筛选
        if ($request->has('due_review') && $request->due_review) {
            $query->whereNotNull('next_review_at')
                  ->where('next_review_at', '<=', now());
        }
        
        // 分页
        $perPage = $request->get('per_page', 20);
        $userWords = $query->paginate($perPage);
        
        return response()->json($userWords);
    }

    /**
     * 创建或更新用户的单词学习记录
     */
    public function store(UserWordRequest $request): JsonResponse
    {
        $userId = Auth::id();
        $data = $request->validated();
        
        // 查找是否已有记录
        $userWord = UserWord::where('user_id', $userId)
            ->where('word_id', $data['word_id'])
            ->first();
            
        if ($userWord) {
            // 更新现有记录
            $userWord->update($data);
            $message = '学习记录已更新';
        } else {
            // 创建新记录
            $userWord = UserWord::create(array_merge(
                $data,
                ['user_id' => $userId]
            ));
            $message = '学习记录已创建';
        }
        
        return response()->json([
            'message' => $message,
            'user_word' => $userWord
        ]);
    }

    /**
     * 显示指定的用户单词学习记录
     */
    public function show(Word $word): JsonResponse
    {
        $userId = Auth::id();
        
        $userWord = UserWord::with(['word', 'book'])
            ->where('user_id', $userId)
            ->where('word_id', $word->id)
            ->first();
            
        if (!$userWord) {
            return response()->json([
                'message' => '未找到学习记录'
            ], 404);
        }
        
        return response()->json($userWord);
    }

    /**
     * 更新用户单词学习记录
     */
    public function update(UserWordRequest $request, Word $word): JsonResponse
    {
        $userId = Auth::id();
        $data = $request->validated();
        
        $userWord = UserWord::where('user_id', $userId)
            ->where('word_id', $word->id)
            ->first();
            
        if (!$userWord) {
            return response()->json([
                'message' => '未找到学习记录'
            ], 404);
        }
        
        $userWord->update($data);
        
        return response()->json([
            'message' => '学习记录已更新',
            'user_word' => $userWord
        ]);
    }

    /**
     * 标记为收藏/取消收藏
     */
    public function toggleFavorite(Word $word): JsonResponse
    {
        $userId = Auth::id();
        
        $userWord = UserWord::where('user_id', $userId)
            ->where('word_id', $word->id)
            ->first();
            
        // 如果不存在学习记录，则创建一个
        if (!$userWord) {
            $userWord = UserWord::create([
                'user_id' => $userId,
                'word_id' => $word->id,
                'word_book_id' => $word->word_book_id,
                'is_favorite' => true
            ]);
            
            return response()->json([
                'message' => '单词已添加到生词本',
                'is_favorite' => true
            ]);
        }
        
        // 切换收藏状态
        $newStatus = !$userWord->is_favorite;
        $userWord->update(['is_favorite' => $newStatus]);
        
        $message = $newStatus ? '单词已添加到生词本' : '单词已从生词本移除';
        
        return response()->json([
            'message' => $message,
            'is_favorite' => $newStatus
        ]);
    }
    
    /**
     * 更新学习状态
     */
    public function updateStatus(Request $request, Word $word): JsonResponse
    {
        Log::info('更新单词状态请求', [
            'word_id' => $word->id,
            'request_data' => $request->all(),
            'request_content_type' => $request->header('Content-Type'),
            'request_method' => $request->method(),
        ]);
        
        try {
            $request->validate([
                'status' => 'required|integer|min:0|max:3',
            ]);
            
            $userId = Auth::id();
            $status = $request->status;
            
            Log::info('验证通过', [
                'user_id' => $userId,
                'status' => $status
            ]);
            
            $userWord = UserWord::where('user_id', $userId)
                ->where('word_id', $word->id)
                ->first();
                
            // 如果不存在学习记录，则创建一个
            if (!$userWord) {
                Log::info('创建新的学习记录', [
                    'word_id' => $word->id,
                    'user_id' => $userId,
                    'status' => $status
                ]);
                
                $userWord = UserWord::create([
                    'user_id' => $userId,
                    'word_id' => $word->id,
                    'word_book_id' => $word->word_book_id,
                    'status' => $status,
                    'review_count' => 0,
                    'correct_count' => 0,
                    'wrong_count' => 0,
                ]);
            } else {
                Log::info('更新现有学习记录', [
                    'user_word_id' => $userWord->id,
                    'old_status' => $userWord->status,
                    'new_status' => $status
                ]);
                
                $userWord->update(['status' => $status]);
            }
            
            $statusNames = [
                UserWord::STATUS_NEW => '未学习',
                UserWord::STATUS_LEARNING => '学习中',
                UserWord::STATUS_MASTERED => '已掌握',
                UserWord::STATUS_DIFFICULT => '困难词'
            ];
            
            $response = [
                'message' => '学习状态已更新为：' . $statusNames[$status],
                'status' => $status,
                'user_word' => $userWord
            ];
            
            Log::info('响应数据', $response);
            
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('更新单词状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => '更新学习状态失败: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 记录复习结果
     */
    public function recordReview(Request $request, Word $word): JsonResponse
    {
        $request->validate([
            'result' => 'required|boolean',
        ]);
        
        $userId = Auth::id();
        $isCorrect = $request->result;
        
        $userWord = UserWord::where('user_id', $userId)
            ->where('word_id', $word->id)
            ->first();
            
        // 如果不存在学习记录，则创建一个
        if (!$userWord) {
            $userWord = UserWord::create([
                'user_id' => $userId,
                'word_id' => $word->id,
                'word_book_id' => $word->word_book_id,
                'status' => UserWord::STATUS_LEARNING,
                'review_count' => 1,
                'correct_count' => $isCorrect ? 1 : 0,
                'wrong_count' => $isCorrect ? 0 : 1,
                'last_review_at' => now()
            ]);
        } else {
            // 更新复习记录
            $userWord->update([
                'review_count' => $userWord->review_count + 1,
                'correct_count' => $userWord->correct_count + ($isCorrect ? 1 : 0),
                'wrong_count' => $userWord->wrong_count + ($isCorrect ? 0 : 1),
                'last_review_at' => now(),
                // 如果之前是未学习状态，则更新为学习中
                'status' => $userWord->status == UserWord::STATUS_NEW 
                    ? UserWord::STATUS_LEARNING 
                    : $userWord->status
            ]);
        }
        
        // 计算下次复习时间 (简单的间隔重复算法)
        $nextReviewDays = $this->calculateNextReviewInterval($userWord);
        $userWord->update([
            'next_review_at' => now()->addDays($nextReviewDays)
        ]);
        
        return response()->json([
            'message' => $isCorrect ? '答对了，记录已更新' : '答错了，记录已更新',
            'user_word' => $userWord
        ]);
    }
    
    /**
     * 计算下次复习间隔 (简单的间隔重复算法)
     */
    private function calculateNextReviewInterval(UserWord $userWord): int
    {
        // 获取当前连续答对次数 (这里简化处理，根据正确率来决定)
        $totalAnswers = $userWord->correct_count + $userWord->wrong_count;
        $correctRate = $totalAnswers > 0 ? $userWord->correct_count / $totalAnswers : 0;
        
        // 基础间隔: 1天、2天、4天、7天、15天、30天
        $baseIntervals = [1, 2, 4, 7, 15, 30];
        
        // 根据复习次数和正确率计算下一个间隔
        $reviewLevel = min(floor($userWord->review_count / 2), count($baseIntervals) - 1);
        
        // 如果正确率低，缩短间隔
        if ($correctRate < 0.6) {
            return 1; // 错误太多，明天再复习
        } elseif ($correctRate < 0.8) {
            return max(1, $baseIntervals[$reviewLevel] / 2); // 减半间隔
        } else {
            return $baseIntervals[$reviewLevel]; // 正常间隔
        }
    }
    
    /**
     * 获取用户的生词本
     */
    public function favorites(): JsonResponse
    {
        $userId = Auth::id();
        
        $favorites = UserWord::with(['word', 'book'])
            ->where('user_id', $userId)
            ->where('is_favorite', true)
            ->orderBy('updated_at', 'desc')
            ->get();
            
        return response()->json($favorites);
    }
} 