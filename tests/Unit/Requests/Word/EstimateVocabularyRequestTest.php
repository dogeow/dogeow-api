<?php

namespace Tests\Unit\Requests\Word;

use App\Http\Requests\Word\EstimateVocabularyRequest;
use Tests\TestCase;

class EstimateVocabularyRequestTest extends TestCase
{
    private EstimateVocabularyRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new EstimateVocabularyRequest;
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_answers_are_required_array(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('answers', $rules);
        $this->assertContains('required', $rules['answers']);
        $this->assertContains('array', $rules['answers']);
        $this->assertContains('min:1', $rules['answers']);
    }

    public function test_answer_item_rules_are_configured(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('answers.*.word_id', $rules);
        $this->assertContains('required', $rules['answers.*.word_id']);
        $this->assertContains('integer', $rules['answers.*.word_id']);
        $this->assertContains('distinct', $rules['answers.*.word_id']);
        $this->assertContains('exists:words,id', $rules['answers.*.word_id']);

        $this->assertArrayHasKey('answers.*.correct', $rules);
        $this->assertContains('required', $rules['answers.*.correct']);
        $this->assertContains('boolean', $rules['answers.*.correct']);
    }
}
