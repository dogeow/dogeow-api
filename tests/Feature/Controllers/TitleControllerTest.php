<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Services\WebPageService;
use App\Services\CacheService;
use Mockery;

class TitleControllerTest extends TestCase
{

    private $webPageService;
    private $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->webPageService = Mockery::mock(WebPageService::class);
        $this->cacheService = Mockery::mock(CacheService::class);
        
        $this->app->instance(WebPageService::class, $this->webPageService);
        $this->app->instance(CacheService::class, $this->cacheService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fetch_returns_cached_data_when_available()
    {
        $url = 'https://example.com';
        $cachedData = [
            'title' => 'Example Domain',
            'description' => 'This domain is for use in illustrative examples',
            'url' => $url,
        ];

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn($cachedData);

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(200)
            ->assertJson($cachedData);
    }

    public function test_fetch_returns_cached_error_when_available()
    {
        $url = 'https://invalid-url.com';
        $cachedError = [
            'error' => '无法获取网页内容',
            'details' => 'Connection timeout',
            'status_code' => 500,
        ];

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn($cachedError);

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(500)
            ->assertJson($cachedError);
    }

    public function test_fetch_fetches_new_data_when_not_cached()
    {
        $url = 'https://example.com';
        $fetchedData = [
            'title' => 'Example Domain',
            'description' => 'This domain is for use in illustrative examples',
            'url' => $url,
        ];

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andReturn($fetchedData);

        $this->cacheService->shouldReceive('putSuccess')
            ->with($url, $fetchedData)
            ->once();

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(200)
            ->assertJson($fetchedData);
    }

    public function test_fetch_handles_service_exception()
    {
        $url = 'https://error-example.com';
        $exception = new \Exception('Network error');

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andThrow($exception);

        $this->cacheService->shouldReceive('putError')
            ->with($url, Mockery::on(function ($errorData) {
                return $errorData['error'] === '请求异常' &&
                       $errorData['details'] === 'Network error' &&
                       $errorData['status_code'] === 500;
            }))
            ->once();

        $response = $this->getJson("/api/fetch-title?url={$url}");

        $response->assertStatus(500)
            ->assertJson([
                'error' => '请求异常',
                'details' => 'Network error',
                'status_code' => 500,
            ]);
    }

    public function test_fetch_returns_400_when_url_missing()
    {
        $response = $this->getJson('/api/fetch-title');

        $response->assertStatus(400)
            ->assertJson(['error' => '缺少url参数']);
    }

    public function test_fetch_returns_400_when_url_empty()
    {
        $response = $this->getJson('/api/fetch-title?url=');

        $response->assertStatus(400)
            ->assertJson(['error' => '缺少url参数']);
    }

    public function test_fetch_with_url_encoding()
    {
        $url = 'https://example.com/path with spaces';
        $encodedUrl = urlencode($url);
        $fetchedData = [
            'title' => 'Example Page',
            'description' => 'A page with spaces in URL',
            'url' => $url,
        ];

        $this->cacheService->shouldReceive('get')
            ->with($url)
            ->once()
            ->andReturn(null);

        $this->webPageService->shouldReceive('fetchContent')
            ->with($url)
            ->once()
            ->andReturn($fetchedData);

        $this->cacheService->shouldReceive('putSuccess')
            ->with($url, $fetchedData)
            ->once();

        $response = $this->getJson("/api/fetch-title?url={$encodedUrl}");

        $response->assertStatus(200)
            ->assertJson($fetchedData);
    }
} 