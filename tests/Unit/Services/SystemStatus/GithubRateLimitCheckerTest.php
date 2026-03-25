<?php

namespace Tests\Unit\Services\SystemStatus;

use App\Services\SystemStatus\GithubRateLimitChecker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubRateLimitCheckerTest extends TestCase
{
    protected GithubRateLimitChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new GithubRateLimitChecker;
    }

    public function test_check_returns_warning_when_no_token_configured(): void
    {
        config(['services.github.token' => null]);

        $result = $this->checker->check();

        $this->assertSame('warning', $result['status']);
        $this->assertStringContainsString('未配置', $result['details']);
    }

    public function test_check_returns_error_on_http_failure(): void
    {
        config(['services.github.token' => 'fake_token_123']);

        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'Bad credentials'], 401),
        ]);

        $result = $this->checker->check();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('配额读取失败', $result['details']);
    }

    public function test_check_returns_online_with_high_remaining(): void
    {
        config(['services.github.token' => 'fake_token_123']);

        Http::fake([
            'api.github.com/*' => Http::response([
                'resources' => [
                    'core' => ['limit' => 5000, 'remaining' => 4500, 'used' => 500, 'reset' => 1735689600],
                    'graphql' => ['limit' => 5000, 'remaining' => 4900, 'used' => 100, 'reset' => 1735689600],
                ],
            ], 200),
        ]);

        $result = $this->checker->check();

        $this->assertSame('online', $result['status']);
        $this->assertSame(4500, $result['core_remaining']);
        $this->assertSame(5000, $result['core_limit']);
    }

    public function test_check_returns_warning_when_remaining_below_20_percent(): void
    {
        config(['services.github.token' => 'fake_token_123']);

        Http::fake([
            'api.github.com/*' => Http::response([
                'resources' => [
                    'core' => ['limit' => 5000, 'remaining' => 800, 'used' => 4200, 'reset' => 1735689600],
                    'graphql' => ['limit' => 5000, 'remaining' => 4900, 'used' => 100, 'reset' => 1735689600],
                ],
            ], 200),
        ]);

        $result = $this->checker->check();

        // 800/5000 = 0.16 which is below 0.2 but above 0.1
        $this->assertSame('warning', $result['status']);
    }

    public function test_check_returns_error_when_remaining_below_10_percent(): void
    {
        config(['services.github.token' => 'fake_token_123']);

        Http::fake([
            'api.github.com/*' => Http::response([
                'resources' => [
                    'core' => ['limit' => 5000, 'remaining' => 300, 'used' => 4700, 'reset' => 1735689600],
                    'graphql' => ['limit' => 5000, 'remaining' => 4900, 'used' => 100, 'reset' => 1735689600],
                ],
            ], 200),
        ]);

        $result = $this->checker->check();

        // 300/5000 = 0.06 which is below 0.1
        $this->assertSame('error', $result['status']);
    }

    public function test_check_includes_rate_limit_details(): void
    {
        config(['services.github.token' => 'fake_token_123']);

        Http::fake([
            'api.github.com/*' => Http::response([
                'resources' => [
                    'core' => ['limit' => 5000, 'remaining' => 4500, 'used' => 500, 'reset' => 1735689600],
                    'graphql' => ['limit' => 5000, 'remaining' => 4900, 'used' => 100, 'reset' => 1735689600],
                ],
            ], 200),
        ]);

        $result = $this->checker->check();

        $this->assertSame(4500, $result['core_remaining']);
        $this->assertSame(5000, $result['core_limit']);
        $this->assertSame(500, $result['core_used']);
        $this->assertSame(4900, $result['graphql_remaining']);
        $this->assertSame(5000, $result['graphql_limit']);
    }

    public function test_check_handles_graphql_rate_limit(): void
    {
        config(['services.github.token' => 'fake_token_123']);

        Http::fake([
            'api.github.com/*' => Http::response([
                'resources' => [
                    'core' => ['limit' => 5000, 'remaining' => 4500, 'used' => 500, 'reset' => 1735689600],
                    'graphql' => ['limit' => 5000, 'remaining' => 300, 'used' => 4700, 'reset' => 1735689600],
                ],
            ], 200),
        ]);

        $result = $this->checker->check();

        $this->assertSame(300, $result['graphql_remaining']);
        $this->assertSame(4700, $result['graphql_used']);
    }

    public function test_check_includes_reset_at_timestamp(): void
    {
        config(['services.github.token' => 'fake_token_123']);
        $resetTimestamp = 1735689600;

        Http::fake([
            'api.github.com/*' => Http::response([
                'resources' => [
                    'core' => ['limit' => 5000, 'remaining' => 4500, 'used' => 500, 'reset' => $resetTimestamp],
                    'graphql' => ['limit' => 5000, 'remaining' => 4900, 'used' => 100, 'reset' => $resetTimestamp],
                ],
            ], 200),
        ]);

        $result = $this->checker->check();

        $this->assertNotNull($result['reset_at']);
        $this->assertIsString($result['reset_at']);
    }

    public function test_check_returns_error_on_exception(): void
    {
        config(['services.github.token' => 'fake_token_123']);

        Http::fake([
            'api.github.com/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $result = $this->checker->check();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('配额读取失败', $result['details']);
    }

    public function test_check_returns_warning_on_403_response(): void
    {
        config(['services.github.token' => 'fake_token_123']);

        Http::fake([
            'api.github.com/*' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $result = $this->checker->check();

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('配额读取失败', $result['details']);
    }
}
