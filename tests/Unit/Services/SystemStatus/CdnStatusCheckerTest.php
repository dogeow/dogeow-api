<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\CdnStatusChecker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CdnStatusCheckerTest extends TestCase
{
    protected CdnStatusChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new CdnStatusChecker;
    }

    public function test_check_returns_warning_when_cdn_url_not_configured(): void
    {
        config(['services.upyun.cdn_url' => null]);

        $result = $this->checker->check();

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('CDN URL 未配置', $result['details']);
    }

    public function test_check_returns_online_when_cdn_is_reachable(): void
    {
        config(['services.upyun.cdn_url' => 'https://cdn.example.com']);

        Http::fake([
            'cdn.example.com/*' => Http::response('', 200),
        ]);

        $result = $this->checker->check();

        $this->assertSame('online', $result['status']);
        $this->assertArrayHasKey('response_time', $result);
    }

    public function test_check_returns_warning_when_cdn_returns_non_success(): void
    {
        config(['services.upyun.cdn_url' => 'https://cdn.example.com']);

        Http::fake([
            'cdn.example.com/*' => Http::response('Not Found', 404),
        ]);

        $result = $this->checker->check();

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('CDN 响应异常', $result['details']);
    }

    public function test_check_returns_error_on_connection_failure(): void
    {
        config(['services.upyun.cdn_url' => 'https://cdn.example.com']);

        Http::fake([
            'cdn.example.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->checker->check();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('CDN 连接失败', $result['details']);
    }

    public function test_check_includes_response_time_on_success(): void
    {
        config(['services.upyun.cdn_url' => 'https://cdn.example.com']);

        Http::fake([
            'cdn.example.com/*' => Http::response('', 200),
        ]);

        $result = $this->checker->check();

        $this->assertSame('online', $result['status']);
        $this->assertArrayHasKey('response_time', $result);
        $this->assertIsFloat($result['response_time']);
        $this->assertStringContainsString('ms', $result['details']);
    }

    public function test_check_returns_warning_when_cdn_returns_500(): void
    {
        config(['services.upyun.cdn_url' => 'https://cdn.example.com']);

        Http::fake([
            'cdn.example.com/*' => Http::response('Internal Server Error', 500),
        ]);

        $result = $this->checker->check();

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('CDN 响应异常', $result['details']);
    }

    public function test_check_returns_warning_when_cdn_returns_403(): void
    {
        config(['services.upyun.cdn_url' => 'https://cdn.example.com']);

        Http::fake([
            'cdn.example.com/*' => Http::response('Forbidden', 403),
        ]);

        $result = $this->checker->check();

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('CDN 响应异常', $result['details']);
    }
}
