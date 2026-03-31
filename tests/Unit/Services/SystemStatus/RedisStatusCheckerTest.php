<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\RedisStatusChecker;
use Illuminate\Support\Facades\Redis;
use Predis\Connection\ConnectionException;
use Predis\Connection\Parameters;
use Predis\Connection\StreamConnection;
use Tests\TestCase;

class RedisStatusCheckerTest extends TestCase
{
    protected RedisStatusChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new RedisStatusChecker;
    }

    public function test_check_returns_online_when_redis_responds(): void
    {
        $result = $this->checker->check();

        $this->assertSame('online', $result['status']);
        $this->assertArrayHasKey('response_time', $result);
    }

    public function test_check_returns_error_on_connection_failure(): void
    {
        Redis::shouldReceive('ping')
            ->once()
            ->andThrow(new ConnectionException(
                new StreamConnection(new Parameters(['host' => '127.0.0.1', 'port' => 6379])),
                'Connection refused'
            ));

        $result = $this->checker->check();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Redis 连接失败', $result['details']);
    }

    public function test_check_includes_response_time(): void
    {
        $result = $this->checker->check();

        $this->assertArrayHasKey('response_time', $result);
        $this->assertIsFloat($result['response_time']);
        $this->assertGreaterThanOrEqual(0, $result['response_time']);
        $this->assertStringContainsString('ms', $result['details']);
    }

    public function test_check_includes_response_time_details(): void
    {
        $result = $this->checker->check();

        $this->assertArrayHasKey('details', $result);
        $this->assertStringContainsString('响应时间', $result['details']);
    }
}
