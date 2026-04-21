<?php

namespace App\Services\Word;

use App\Models\Word\Word;
use Illuminate\Support\Collection;

class VocabularyEstimateService
{
    private const SMOOTHING_CORRECT_BONUS = 5;

    private const SMOOTHING_TESTED_BONUS = 10;

    /**
     * @param  array<int, array{word_id:int, correct:bool}>  $answers
     * @return array{
     *     estimated_vocabulary_size:int,
     *     accuracy:float,
     *     confidence:string,
     *     tested_count:int,
     *     correct_count:int
     * }
     */
    public function estimate(array $answers): array
    {
        $measurableAnswers = $this->normalizeAnswers($answers);
        $testedCount = $measurableAnswers->count();
        $correctCount = $measurableAnswers->where('correct', true)->count();
        $accuracy = ($correctCount + self::SMOOTHING_CORRECT_BONUS) / ($testedCount + self::SMOOTHING_TESTED_BONUS);
        $totalQuizWords = $this->getTotalQuizWords();

        return [
            'estimated_vocabulary_size' => (int) round($accuracy * $totalQuizWords),
            'accuracy' => round($accuracy, 4),
            'confidence' => $this->resolveConfidence($testedCount),
            'tested_count' => $testedCount,
            'correct_count' => $correctCount,
        ];
    }

    /**
     * 仅保留可测单词，并按 content 小写去重。
     * 若同一 content 重复出现，使用最后一次答题结果。
     *
     * @param  array<int, array{word_id:int, correct:bool}>  $answers
     * @return Collection<int, array{word_id:int, correct:bool, normalized_content:string}>
     */
    private function normalizeAnswers(array $answers): Collection
    {
        $wordIds = collect($answers)
            ->pluck('word_id')
            ->unique()
            ->values();

        $measurableWords = Word::query()
            ->select(['id', 'content'])
            ->whereIn('id', $wordIds)
            ->whereNotNull('explanation')
            ->whereRaw("TRIM(explanation) <> ''")
            ->get()
            ->mapWithKeys(fn (Word $word): array => [
                $word->id => mb_strtolower((string) $word->content),
            ]);

        return collect($answers)
            ->reduce(function (Collection $carry, array $answer) use ($measurableWords): Collection {
                $normalizedContent = $measurableWords->get($answer['word_id']);

                if ($normalizedContent === null || $normalizedContent === '') {
                    return $carry;
                }

                $carry->put($normalizedContent, [
                    'word_id' => $answer['word_id'],
                    'correct' => (bool) $answer['correct'],
                    'normalized_content' => $normalizedContent,
                ]);

                return $carry;
            }, collect())
            ->values();
    }

    private function getTotalQuizWords(): int
    {
        return (int) Word::query()
            ->whereNotNull('explanation')
            ->whereRaw("TRIM(explanation) <> ''")
            ->selectRaw('COUNT(DISTINCT LOWER(content)) as aggregate')
            ->value('aggregate');
    }

    private function resolveConfidence(int $testedCount): string
    {
        return match (true) {
            $testedCount < 15 => 'low',
            $testedCount < 40 => 'medium',
            default => 'high',
        };
    }
}
