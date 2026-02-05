<?php

namespace App\Http\Controllers\Api\Word;

use App\Http\Controllers\Controller;
use App\Http\Requests\Word\MarkWordRequest;
use App\Http\Resources\Word\WordResource;
use App\Models\Word\Book;
use App\Models\Word\UserSetting;
use App\Models\Word\UserWord;
use App\Models\Word\Word;
use App\Services\EbbinghausService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LearningController extends Controller
{
    public function __construct(
        private readonly EbbinghausService $ebbinghausService
    ) {}

    /**
     * 获取今日学习单词
     */
    public function getDailyWords(): AnonymousResourceCollection
    {
        $user = Auth::user();
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'daily_new_words' => 10,
                'review_multiplier' => 2,
                'is_auto_pronounce' => true,
            ]
        );

        if (!$setting->current_book_id) {
            return WordResource::collection(collect());
        }

        $book = Book::findOrFail($setting->current_book_id);
        $dailyCount = $setting->daily_new_words;

        // 获取用户已学习的单词ID（该单词书下的）
        $learnedWordIds = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $book->id)
            ->pluck('word_id');

        // 获取未学习的单词（通过多对多关系查询）
        $words = $book->words()
            ->with('educationLevels')
            ->whereNotIn('words.id', $learnedWordIds)
            ->limit($dailyCount)
            ->get();

        return WordResource::collection($words);
    }

    /**
     * 获取今日复习单词（艾宾浩斯算法）
     */
    public function getReviewWords(): AnonymousResourceCollection
    {
        $user = Auth::user();
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'daily_new_words' => 10,
                'review_multiplier' => 2,
                'is_auto_pronounce' => true,
            ]
        );

        $reviewCount = $setting->daily_new_words * $setting->review_multiplier;

        // 获取需要复习的单词（下次复习时间已到）
        $userWords = UserWord::where('user_id', $user->id)
            ->where('status', '!=', 0) // 已学习过的
            ->where('next_review_at', '<=', now())
            ->with('word')
            ->orderBy('next_review_at')
            ->limit($reviewCount)
            ->get();

        $words = $userWords->map(fn($userWord) => $userWord->word);

        return WordResource::collection($words);
    }

    /**
     * 标记单词（记住/忘记）
     */
    public function markWord(int $id, MarkWordRequest $request): JsonResponse
    {
        $user = Auth::user();
        $remembered = $request->validated()['remembered'];

        $word = Word::findOrFail($id);
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'daily_new_words' => 10,
                'review_multiplier' => 2,
                'is_auto_pronounce' => true,
            ]
        );

        // 从用户设置中获取当前单词书ID
        $bookId = $setting->current_book_id;

        DB::transaction(function () use ($user, $word, $remembered, $bookId) {
            // 获取或创建用户单词记录
            $userWord = UserWord::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'word_id' => $word->id,
                    'word_book_id' => $bookId,
                ],
                [
                    'status' => 1, // 学习中
                    'stage' => 0,
                    'ease_factor' => 2.50,
                ]
            );

            // 如果是新单词，设置初始状态
            if ($userWord->status === 0) {
                $userWord->status = 1;
                $userWord->stage = 0;
                $userWord->ease_factor = 2.50;
            }

            // 处理复习结果
            $this->ebbinghausService->processReview($userWord, $remembered);
            $userWord->save();
        });

        return response()->json([
            'message' => '单词标记成功',
        ]);
    }

    /**
     * 获取学习进度统计
     */
    public function getProgress(): JsonResponse
    {
        $user = Auth::user();
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'daily_new_words' => 10,
                'review_multiplier' => 2,
                'is_auto_pronounce' => true,
            ]
        );

        $bookId = $setting->current_book_id;
        if (!$bookId) {
            return response()->json([
                'total_words' => 0,
                'learned_words' => 0,
                'mastered_words' => 0,
                'difficult_words' => 0,
                'progress_percentage' => 0,
            ]);
        }

        $book = Book::findOrFail($bookId);
        $totalWords = $book->total_words;

        $learnedWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $bookId)
            ->where('status', '!=', 0)
            ->count();

        $masteredWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $bookId)
            ->where('status', 2)
            ->count();

        $difficultWords = UserWord::where('user_id', $user->id)
            ->where('word_book_id', $bookId)
            ->where('status', 3)
            ->count();

        $progressPercentage = $totalWords > 0 
            ? round(($learnedWords / $totalWords) * 100, 2) 
            : 0;

        return response()->json([
            'total_words' => $totalWords,
            'learned_words' => $learnedWords,
            'mastered_words' => $masteredWords,
            'difficult_words' => $difficultWords,
            'progress_percentage' => $progressPercentage,
        ]);
    }

    /**
     * 更新单词数据（修正释义、例句等）
     */
    public function updateWord(int $id): JsonResponse
    {
        $word = Word::findOrFail($id);

        $validated = request()->validate([
            'explanation' => 'sometimes|array',
            'explanation.zh' => 'sometimes|string',
            'explanation.en' => 'sometimes|string',
            'example_sentences' => 'sometimes|array',
            'example_sentences.*.en' => 'required_with:example_sentences|string',
            'example_sentences.*.zh' => 'sometimes|string',
            'phonetic_us' => 'sometimes|string|nullable',
        ]);

        $word->update($validated);

        return response()->json([
            'message' => '单词更新成功',
            'word' => new WordResource($word),
        ]);
    }
}
