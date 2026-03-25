<?php

namespace Tests\Unit\Services\Cache;

use App\Services\Cache\RedisLockService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

class RedisLockServiceTest extends TestCase
{
    protected RedisLockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RedisLockService;
        // Clean up any existing locks
        try {
            $redis = Redis::connection();
            $keys = $redis->keys('lock:*');
            if (! empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Throwable) {
            // Redis may not be available in test environment
        }
    }

    public function test_lock_returns_token_on_success(): void
    {
        $token = $this->service->lock('test_lock_key');

        $this->assertNotFalse($token);
        $this->assertIsString($token);
        $this->assertSame(32, strlen($token));

        // Cleanup
        $this->service->release('test_lock_key', $token);
    }

    public function test_lock_returns_false_when_already_locked(): void
    {
        $token1 = $this->service->lock('already_locked');
        $token2 = $this->service->lock('already_locked');

        $this->assertNotFalse($token1);
        $this->assertFalse($token2);

        // Cleanup
        $this->service->release('already_locked', $token1);
    }

    public function test_lock_with_custom_ttl(): void
    {
        $token = $this->service->lock('custom_ttl_lock', 5);

        $this->assertNotFalse($token);

        // Cleanup
        $this->service->release('custom_ttl_lock', $token);
    }

    public function test_release_returns_true_with_correct_token(): void
    {
        $token = $this->service->lock('release_test');
        $released = $this->service->release('release_test', $token);

        $this->assertTrue($released);
    }

    public function test_release_returns_false_with_wrong_token(): void
    {
        $token = $this->service->lock('release_wrong_token');
        $released = $this->service->release('release_wrong_token', 'wrong_token_' . Str::random(20));

        $this->assertFalse($released);

        // Cleanup
        $this->service->release('release_wrong_token', $token);
    }

    public function test_release_returns_false_when_lock_not_exists(): void
    {
        $released = $this->service->release('nonexistent_lock', Str::random(32));

        $this->assertFalse($released);
    }

    public function test_extend_returns_true_with_correct_token(): void
    {
        $token = $this->service->lock('extend_test', 10);
        $extended = $this->service->extend('extend_test', $token, 20);

        $this->assertTrue($extended);

        // Cleanup
        $this->service->release('extend_test', $token);
    }

    public function test_extend_returns_false_with_wrong_token(): void
    {
        $token = $this->service->lock('extend_wrong_token');
        $extended = $this->service->extend('extend_wrong_token', 'wrong_token_' . Str::random(20));

        $this->assertFalse($extended);

        // Cleanup
        $this->service->release('extend_wrong_token', $token);
    }

    public function test_extend_returns_false_when_lock_not_exists(): void
    {
        $extended = $this->service->extend('nonexistent_extend', Str::random(32));

        $this->assertFalse($extended);
    }

    public function test_is_locked_returns_true_when_locked(): void
    {
        $token = $this->service->lock('is_locked_test');

        $this->assertTrue($this->service->isLocked('is_locked_test'));

        // Cleanup
        $this->service->release('is_locked_test', $token);
    }

    public function test_is_locked_returns_false_when_not_locked(): void
    {
        $result = $this->service->isLocked('not_locked_test');

        $this->assertFalse($result);
    }

    public function test_wait_and_lock_returns_token_on_first_try(): void
    {
        $token = $this->service->waitAndLock('wait_lock_first', 5, 0, 0);

        $this->assertNotFalse($token);

        // Cleanup
        $this->service->release('wait_lock_first', $token);
    }

    public function test_wait_and_lock_returns_false_after_max_retries(): void
    {
        // Hold the lock first
        $heldToken = $this->service->lock('wait_lock_blocked', 10);
        $this->assertNotFalse($heldToken);

        // Try to wait and lock - should fail after retries
        $token = $this->service->waitAndLock('wait_lock_blocked', 5, 2, 10);

        $this->assertFalse($token);

        // Cleanup
        $this->service->release('wait_lock_blocked', $heldToken);
    }

    public function test_wait_and_lock_succeeds_after_lock_released(): void
    {
        $heldToken = $this->service->lock('wait_lock_released', 1);

        // Release quickly
        $this->service->release('wait_lock_released', $heldToken);

        // Now wait and lock should succeed
        $token = $this->service->waitAndLock('wait_lock_released', 5, 3, 10);

        $this->assertNotFalse($token);

        // Cleanup
        $this->service->release('wait_lock_released', $token);
    }

    public function test_lock_token_is_random_and_unique(): void
    {
        $token1 = $this->service->lock('random_token_test_1', 10);
        $this->service->release('random_token_test_1', $token1);

        $token2 = $this->service->lock('random_token_test_2', 10);
        $this->service->release('random_token_test_2', $token2);

        $this->assertNotSame($token1, $token2);
    }
}
