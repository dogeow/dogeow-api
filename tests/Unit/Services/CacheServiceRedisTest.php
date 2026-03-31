<?php

namespace Tests\Unit\Services;

use App\Services\Cache\CacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * @group redis
 * @group skip
 */
class CacheServiceRedisTest extends TestCase
{
    use RefreshDatabase;

    protected CacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'redis');

        $this->cacheService = new CacheService;
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_forget_by_prefix_deletes_matching_keys_from_redis()
    {
        $this->markTestSkipped('Implementation detail test - covered by CacheServiceTest integration tests');
    }

    public function test_forget_by_prefix_no_op_when_no_keys_found()
    {
        $this->markTestSkipped('Implementation detail test - covered by CacheServiceTest integration tests');
    }
}
