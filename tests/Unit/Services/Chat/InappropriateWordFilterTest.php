<?php

namespace Tests\Unit\Services\Chat;

use App\Services\Chat\InappropriateWordFilter;
use Tests\TestCase;

class InappropriateWordFilterTest extends TestCase
{
    protected InappropriateWordFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filter = new InappropriateWordFilter;
    }

    public function test_check_returns_no_violations_for_clean_message(): void
    {
        $result = $this->filter->check('Hello, this is a nice message!');

        $this->assertFalse($result['has_violations']);
        $this->assertEmpty($result['violations']);
        $this->assertEquals('low', $result['severity']);
        $this->assertEquals('Hello, this is a nice message!', $result['filtered_message']);
        $this->assertFalse($result['action_required']);
    }

    public function test_check_detects_low_severity_word(): void
    {
        $result = $this->filter->check('This is spam content');

        $this->assertTrue($result['has_violations']);
        $this->assertCount(1, $result['violations']);
        $this->assertEquals('low', $result['severity']);
        $this->assertEquals('spam', $result['violations'][0]['word']);
        $this->assertEquals('inappropriate_word', $result['violations'][0]['type']);
    }

    public function test_check_detects_high_severity_word(): void
    {
        $result = $this->filter->check('I hate you');

        $this->assertTrue($result['has_violations']);
        $this->assertEquals('high', $result['severity']);
        $this->assertTrue($result['action_required']);
    }

    public function test_check_filters_and_replaces_word(): void
    {
        $result = $this->filter->check('This is spam content');

        $this->assertStringContainsString('****', $result['filtered_message']);
        $this->assertStringNotContainsString('spam', $result['filtered_message']);
    }

    public function test_check_handles_case_insensitive_detection(): void
    {
        $result = $this->filter->check('This is SPAM and HATE');

        $this->assertCount(2, $result['violations']);
    }

    public function test_check_action_required_for_high_severity(): void
    {
        $result = $this->filter->check('violence is bad');

        $this->assertTrue($result['action_required']);
    }

    public function test_check_action_required_for_multiple_violations(): void
    {
        $result = $this->filter->check('spam spam stupid');

        $this->assertTrue($result['action_required']);
    }

    public function test_get_word_severity_returns_high_for_hate(): void
    {
        $severity = $this->filter->getWordSeverity('hate');

        $this->assertEquals('high', $severity);
    }

    public function test_get_word_severity_returns_high_for_violence(): void
    {
        $severity = $this->filter->getWordSeverity('violence');

        $this->assertEquals('high', $severity);
    }

    public function test_get_word_severity_returns_low_for_other_words(): void
    {
        $severity = $this->filter->getWordSeverity('spam');

        $this->assertEquals('low', $severity);
    }

    public function test_get_inappropriate_words_returns_array(): void
    {
        $words = $this->filter->getInappropriateWords();

        $this->assertIsArray($words);
        $this->assertContains('stupid', $words);
        $this->assertContains('spam', $words);
        $this->assertContains('hate', $words);
        $this->assertContains('violence', $words);
    }

    public function test_get_replacement_returns_correct_replacement(): void
    {
        $replacement = $this->filter->getReplacement('spam');

        $this->assertEquals('****', $replacement);
    }

    public function test_get_replacement_returns_null_for_unknown_word(): void
    {
        $replacement = $this->filter->getReplacement('unknown');

        $this->assertNull($replacement);
    }

    public function test_check_detects_multiple_different_words(): void
    {
        $result = $this->filter->check('I hate violence and spam');

        $this->assertCount(3, $result['violations']);
    }

    public function test_check_does_not_detect_word_as_part_of_other_word(): void
    {
        $result = $this->filter->check('spammy is not a bad word');

        $this->assertTrue($result['has_violations']);
    }
}
