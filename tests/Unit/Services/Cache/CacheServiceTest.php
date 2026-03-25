<?php

namespace Tests\Unit\Services\Cache;

use App\Services\Cache\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class CacheServiceTest extends TestCase
{
    protected CacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CacheService;
        Cache::flush();
    }

    public function test_get_returns_cached_data(): void
    {
        Cache::put('app:' . md5('test_key'), 'test_value', 3600);

        $result = $this->service->get('test_key');

        $this->assertSame('test_value', $result);
    }

    public function test_get_returns_null_when_not_found(): void
    {
        $result = $this->service->get('nonexistent_key');

        $this->assertNull($result);
    }

    public function test_put_stores_data_with_ttl(): void
    {
        $this->service->put('ttl_key', 'ttl_value', 60);

        $result = $this->service->get('ttl_key');

        $this->assertSame('ttl_value', $result);
    }

    public function test_put_stores_data_with_custom_prefix(): void
    {
        $this->service->put('prefixed_key', 'prefixed_value', 3600, 'custom_prefix');

        $result = $this->service->get('prefixed_key', 'custom_prefix');

        $this->assertSame('prefixed_value', $result);
    }

    public function test_put_success_sets_24_hour_ttl(): void
    {
        $this->service->putSuccess('success_key', 'success_value');

        $result = $this->service->get('success_key');

        $this->assertSame('success_value', $result);
    }

    public function test_put_error_sets_30_minute_ttl(): void
    {
        $this->service->putError('error_key', 'error_value');

        $result = $this->service->get('error_key');

        $this->assertSame('error_value', $result);
    }

    public function test_forget_removes_cached_data(): void
    {
        Cache::put('app:' . md5('forget_key'), 'forget_value', 3600);

        $this->service->forget('forget_key');

        $result = $this->service->get('forget_key');
        $this->assertNull($result);
    }

    public function test_forget_by_prefix_removes_matching_keys(): void
    {
        $this->service->put('item_1', 'value1', 3600, 'prefix_test');
        $this->service->put('item_2', 'value2', 3600, 'prefix_test');

        $this->service->forgetByPrefix('prefix_test');

        $this->assertNull($this->service->get('item_1', 'prefix_test'));
        $this->assertNull($this->service->get('item_2', 'prefix_test'));
    }

    public function test_remember_executes_callback_when_key_missing(): void
    {
        $callbackExecuted = false;
        $callback = function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return 'computed_value';
        };

        $result = $this->service->remember('remember_key', $callback, 3600);

        $this->assertTrue($callbackExecuted);
        $this->assertSame('computed_value', $result);
    }

    public function test_remember_returns_cached_value_when_exists(): void
    {
        Cache::put('app:' . md5('remember_existing'), 'cached_value', 3600);

        $callbackExecuted = false;
        $callback = function () use (&$callbackExecuted) {
            $callbackExecuted = true;

            return 'new_value';
        };

        $result = $this->service->remember('remember_existing', $callback);

        $this->assertFalse($callbackExecuted);
        $this->assertSame('cached_value', $result);
    }

    public function test_build_cache_key_uses_md5_hash(): void
    {
        Cache::put('app:' . md5('hash_key'), 'hash_value', 3600);

        $result = $this->service->get('hash_key');

        $this->assertSame('hash_value', $result);
    }

    public function test_build_cache_key_uses_custom_prefix(): void
    {
        $this->service->put('custom_key', 'custom_value', 3600, 'my_prefix');

        $result = $this->service->get('custom_key', 'my_prefix');

        $this->assertSame('custom_value', $result);
    }

    public function test_get_title_favicon_returns_null_when_not_cached(): void
    {
        $result = $this->service->getTitleFavicon('https://example.com');

        $this->assertNull($result);
    }

    public function test_put_title_favicon_success_caches_data(): void
    {
        $data = ['title' => 'Example', 'favicon' => '/favicon.ico'];
        $this->service->putTitleFaviconSuccess('https://example.com', $data);

        $result = $this->service->getTitleFavicon('https://example.com');

        $this->assertSame($data, $result);
    }

    public function test_put_title_favicon_error_caches_error_data(): void
    {
        $errorData = ['error' => 'Failed to fetch'];
        $this->service->putTitleFaviconError('https://example.com', $errorData);

        $result = $this->service->getTitleFavicon('https://example.com');

        $this->assertSame($errorData, $result);
    }
}
