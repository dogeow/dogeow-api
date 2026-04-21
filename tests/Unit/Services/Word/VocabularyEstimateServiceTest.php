<?php

namespace Tests\Unit\Services\Word;

use App\Models\Word\Word;
use App\Services\Word\VocabularyEstimateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VocabularyEstimateServiceTest extends TestCase
{
    use RefreshDatabase;

    private VocabularyEstimateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VocabularyEstimateService;
    }

    public function test_it_estimates_vocabulary_with_smoothed_accuracy(): void
    {
        $alpha = Word::create([
            'content' => 'Alpha',
            'explanation' => 'alpha explanation',
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ]);
        $beta = Word::create([
            'content' => 'beta',
            'explanation' => 'beta explanation',
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ]);
        Word::create([
            'content' => 'gamma',
            'explanation' => 'gamma explanation',
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ]);
        Word::create([
            'content' => 'delta',
            'explanation' => '',
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ]);

        $result = $this->service->estimate([
            ['word_id' => $alpha->id, 'correct' => true],
            ['word_id' => $beta->id, 'correct' => false],
        ]);

        $this->assertSame(2, $result['tested_count']);
        $this->assertSame(1, $result['correct_count']);
        $this->assertSame(0.5, $result['accuracy']);
        $this->assertSame(2, $result['estimated_vocabulary_size']);
        $this->assertSame('low', $result['confidence']);
    }

    public function test_it_uses_latest_answer_for_same_word_and_ignores_unmeasurable_words(): void
    {
        $apple = Word::create([
            'content' => 'Apple',
            'explanation' => 'first apple',
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ]);
        $banana = Word::create([
            'content' => 'Banana',
            'explanation' => 'banana explanation',
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ]);
        $empty = Word::create([
            'content' => 'Hidden',
            'explanation' => '   ',
            'example_sentences' => [],
            'difficulty' => 1,
            'frequency' => 1,
        ]);

        $result = $this->service->estimate([
            ['word_id' => $apple->id, 'correct' => false],
            ['word_id' => $apple->id, 'correct' => true],
            ['word_id' => $banana->id, 'correct' => true],
            ['word_id' => $empty->id, 'correct' => true],
        ]);

        $this->assertSame(2, $result['tested_count']);
        $this->assertSame(2, $result['correct_count']);
        $this->assertSame(0.5833, $result['accuracy']);
        $this->assertSame(1, $result['estimated_vocabulary_size']);
    }
}
