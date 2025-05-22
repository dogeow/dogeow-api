<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    private const CACHE_PREFIX = 'title_favicon_';
    private const SUCCESS_CACHE_TTL = 86400; // 24 hours
    private const ERROR_CACHE_TTL = 1800; // 30 minutes

    public function get(string $url): ?array
    {
        return Cache::get($this->getCacheKey($url));
    }

    public function putSuccess(string $url, array $data): void
    {
        Cache::put($this->getCacheKey($url), $data, now()->addSeconds(self::SUCCESS_CACHE_TTL));
    }

    public function putError(string $url, array $errorData): void
    {
        Cache::put($this->getCacheKey($url), $errorData, now()->addSeconds(self::ERROR_CACHE_TTL));
    }

    private function getCacheKey(string $url): string
    {
        return self::CACHE_PREFIX . md5($url);
    }
} 