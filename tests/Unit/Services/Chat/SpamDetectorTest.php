<?php

namespace Tests\Unit\Services\Chat;

use App\Models\Chat\ChatMessage;
use App\Services\Chat\SpamDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SpamDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected SpamDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new SpamDetector;
        Cache::flush();
    }

    public function test_detect_returns_no_violations_for_normal_message(): void
    {
        $result = $this->detector->detect('Hello, this is a normal message.', 1, 1);

        $this->assertFalse($result['is_spam']);
        $this->assertEmpty($result['violations']);
        $this->assertEquals('low', $result['severity']);
        $this->assertFalse($result['action_required']);
    }

    public function test_detect_flags_high_message_frequency(): void
    {
        $userId = 1;
        $roomId = 1;

        // Send many messages to trigger frequency check
        for ($i = 0; $i < 6; $i++) {
            $this->detector->detect("Message $i", $userId, $roomId);
        }

        $result = $this->detector->detect('Another message', $userId, $roomId);

        $this->assertTrue($result['is_spam']);
        $this->assertTrue($result['action_required']);
    }

    public function test_detect_flags_duplicate_messages(): void
    {
        $userId = 1;
        $roomId = 1;
        $message = 'Same message';

        ChatMessage::factory()->count(3)->create([
            'user_id' => $userId,
            'room_id' => $roomId,
            'message' => $message,
            'created_at' => now(),
        ]);

        $result = $this->detector->detect($message, $userId, $roomId);

        $this->assertTrue($result['is_spam']);
    }

    public function test_detect_flags_excessive_caps(): void
    {
        $result = $this->detector->detect('THIS IS ALL CAPS AND VERY LOUD MESSAGE!!!', 1, 1);

        $this->assertTrue($result['is_spam']);
    }

    public function test_detect_ignores_short_messages_for_caps(): void
    {
        $result = $this->detector->detect('HELLO', 1, 1);

        $this->assertFalse($result['violations']);
    }

    public function test_detect_flags_character_repetition(): void
    {
        $result = $this->detector->detect('Helloooooooooooooo', 1, 1);

        $this->assertTrue($result['is_spam']);
    }

    public function test_detect_ignores_short_messages_for_repetition(): void
    {
        $result = $this->detector->detect('Hellooooo', 1, 1);

        $this->assertFalse($result['violations']);
    }

    public function test_detect_flags_url_spam_with_too_many_urls(): void
    {
        $result = $this->detector->detect(
            'Check out https://a.com https://b.com https://c.com https://d.com',
            1,
            1
        );

        $this->assertTrue($result['is_spam']);
    }

    public function test_detect_flags_suspicious_url_patterns(): void
    {
        $result = $this->detector->detect(
            'Click here for free money: https://bit.ly/scam',
            1,
            1
        );

        $this->assertTrue($result['is_spam']);
    }

    public function test_check_message_frequency_returns_not_spam_for_normal_usage(): void
    {
        $result = $this->detector->checkMessageFrequency(1, 1);

        $this->assertFalse($result['is_spam']);
        $this->assertEquals(1, $result['message_count']);
        $this->assertEquals(5, $result['limit']);
    }

    public function test_check_message_frequency_returns_spam_when_over_limit(): void
    {
        // First, send messages up to the limit
        for ($i = 0; $i < 5; $i++) {
            $this->detector->checkMessageFrequency(1, 1);
        }

        $result = $this->detector->checkMessageFrequency(1, 1);

        $this->assertTrue($result['is_spam']);
        $this->assertGreaterThan(5, $result['message_count']);
    }

    public function test_check_excessive_caps_returns_not_spam_for_normal_caps(): void
    {
        $result = $this->detector->checkExcessiveCaps('Hello World');

        $this->assertFalse($result['is_spam']);
    }

    public function test_check_excessive_caps_returns_spam_for_70_percent_caps(): void
    {
        $result = $this->detector->checkExcessiveCaps('THIS IS MOSTLY CAPS');

        $this->assertTrue($result['is_spam']);
        $this->assertArrayHasKey('caps_ratio', $result);
    }

    public function test_check_character_repetition_returns_not_spam_for_normal_text(): void
    {
        $result = $this->detector->checkCharacterRepetition('This is normal text');

        $this->assertFalse($result['is_spam']);
    }

    public function test_check_character_repetition_returns_spam_for_excessive_repetition(): void
    {
        $result = $this->detector->checkCharacterRepetition('Helloooooooooooo');

        $this->assertTrue($result['is_spam']);
    }

    public function test_check_url_spam_returns_not_spam_for_normal_urls(): void
    {
        $result = $this->detector->checkUrlSpam('Check https://example.com');

        $this->assertFalse($result['is_spam']);
        $this->assertEquals(1, $result['url_count']);
    }

    public function test_check_url_spam_returns_spam_for_shortened_urls(): void
    {
        $result = $this->detector->checkUrlSpam('Check https://bit.ly/abc');

        $this->assertTrue($result['is_spam']);
        $this->assertGreaterThan(0, $result['suspicious_urls']);
    }

    public function test_check_url_spam_returns_spam_for_promo_patterns(): void
    {
        $result = $this->detector->checkUrlSpam('Free money click here');

        $this->assertTrue($result['is_spam']);
    }

    public function test_check_duplicate_messages_returns_not_spam_for_unique_messages(): void
    {
        ChatMessage::factory()->create([
            'user_id' => 1,
            'room_id' => 1,
            'message' => 'First message',
            'created_at' => now(),
        ]);

        $result = $this->detector->checkDuplicateMessages('Second message', 1, 1);

        $this->assertFalse($result['is_spam']);
        $this->assertEquals(0, $result['duplicate_count']);
    }
}
