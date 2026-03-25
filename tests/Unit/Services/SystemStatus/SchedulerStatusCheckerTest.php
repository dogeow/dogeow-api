<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\SchedulerStatusChecker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SchedulerStatusCheckerTest extends TestCase
{
    protected SchedulerStatusChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new SchedulerStatusChecker;
    }

    public function test_check_returns_warning_when_no_heartbeat(): void
    {
        Cache::forget('scheduler:heartbeat');

        $result = $this->checker->check();

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('未检测到', $result['details']);
    }

    public function test_check_returns_online_when_heartbeat_is_recent(): void
    {
        Cache::put('scheduler:heartbeat', Carbon::now()->toDateTimeString());

        $result = $this->checker->check();

        $this->assertSame('online', $result['status']);
    }

    public function test_check_returns_error_when_heartbeat_exceeds_threshold(): void
    {
        Cache::put('scheduler:heartbeat', Carbon::now()->subSeconds(120)->toDateTimeString());

        $result = $this->checker->check();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('已停止', $result['details']);
    }

    public function test_check_includes_last_run_timestamp(): void
    {
        $heartbeatTime = Carbon::now()->subSeconds(30)->toDateTimeString();
        Cache::put('scheduler:heartbeat', $heartbeatTime);

        $result = $this->checker->check();

        $this->assertArrayHasKey('last_run', $result);
        $this->assertNotNull($result['last_run']);
    }

    public function test_check_returns_error_on_exception(): void
    {
        Cache::put('scheduler:heartbeat', 'invalid-date-format-xyz');

        $result = $this->checker->check();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('调度器状态检查失败', $result['details']);
    }

    public function test_check_online_includes_human_readable_details(): void
    {
        Cache::put('scheduler:heartbeat', Carbon::now()->subSeconds(5)->toDateTimeString());

        $result = $this->checker->check();

        $this->assertSame('online', $result['status']);
        $this->assertStringContainsString('上次运行', $result['details']);
    }
}
