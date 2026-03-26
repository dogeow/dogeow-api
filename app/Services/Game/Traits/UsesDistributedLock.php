<?php

namespace App\Services\Game\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Provides distributed locking and idempotency handling for shop operations
 * to eliminate DRY violation in buy/sell methods.
 *
 * Key design principles:
 * 1. Idempotency keys prevent duplicate submissions (same request processed multiple times)
 * 2. Distributed locks prevent concurrent modifications to the same character/item
 * 3. Both mechanisms work together - idempotency uses Redis SET NX for atomic check-and-set
 */
trait UsesDistributedLock
{
    /** @var array<string, string> Stack of active idempotency keys for cleanup on error */
    private array $activeIdempotencyKeys = [];

    /**
     * Execute callback with idempotency check using Redis SET NX combined with distributed lock.
     *
     * This method properly combines idempotency and locking:
     * - Uses Redis SET NX for atomic "check if already processing/completed"
     * - If not completed, acquires a distributed lock and executes the callback
     * - Caches and returns the result
     *
     * @param  int  $characterId  Character ID
     * @param  string  $lockKey  Distributed lock key
     * @param  string  $operation  Operation name (buy/sell)
     * @param  string|null  $idempotencyKey  Idempotency key
     * @param  callable  $callback  Operation to execute
     * @param  int  $lockTimeoutSeconds  Lock timeout
     * @return array Result from callback (cached if already computed)
     *
     * @throws \RuntimeException When request is already being processed
     */
    protected function executeWithIdempotencyAndLock(
        int $characterId,
        string $lockKey,
        string $operation,
        ?string $idempotencyKey,
        callable $callback,
        int $lockTimeoutSeconds = 10,
    ): array {
        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $this->executeWithDistributedLock($lockKey, $callback, $lockTimeoutSeconds);
        }

        $cacheKey = $this->getIdempotencyCacheKey($characterId, $idempotencyKey, $operation);

        // Try to acquire processing lock with SET NX (atomic check-and-set)
        // This prevents duplicate submissions from racing each other
        $acquired = Redis::set($cacheKey, 'processing', 'EX', $this->getProcessingTtlSeconds(), 'NX');

        if (! $acquired) {
            // Lock not acquired - either currently processing or already completed
            $cachedResult = $this->getIdempotencyResult($characterId, $idempotencyKey, $operation);
            if ($cachedResult !== null) {
                // Already completed - return cached result (idempotent behavior)
                // Cleanup the Redis key since result is already cached
                $this->cleanupIdempotencyKey($cacheKey);

                return $cachedResult;
            }

            // Still processing by another request - prevent duplicate submission
            throw new \RuntimeException('请求正在处理中，请稍后重试');
        }

        // Successfully acquired processing lock
        $this->activeIdempotencyKeys[$cacheKey] = $cacheKey;

        try {
            // Now acquire the distributed lock for the actual operation
            // This ensures only one process executes the business logic at a time
            return $this->executeWithDistributedLock($lockKey, function () use ($characterId, $idempotencyKey, $operation, $callback) {
                // Double-check: another process might have completed while we were waiting for lock
                $cachedResult = $this->getIdempotencyResult($characterId, $idempotencyKey, $operation);
                if ($cachedResult !== null) {
                    return $cachedResult;
                }

                // Execute the actual operation
                $result = $callback();

                // Cache the result for future idempotent requests
                $this->cacheIdempotencyResult($characterId, $idempotencyKey, $operation, $result);

                return $result;
            }, $lockTimeoutSeconds);
        } finally {
            // Release processing lock
            $this->cleanupIdempotencyKey($cacheKey);
        }
    }

    /**
     * Execute callback with distributed lock using Cache::lock()
     *
     * @param  string  $lockKey  Lock key
     * @param  callable  $callback  Operation to execute
     * @param  int  $timeoutSeconds  Lock timeout
     * @return array Result from callback
     *
     * @throws \RuntimeException When lock cannot be acquired
     */
    protected function executeWithDistributedLock(
        string $lockKey,
        callable $callback,
        int $timeoutSeconds = 10,
    ): array {
        $lock = Cache::lock($lockKey, $timeoutSeconds);

        if (! $lock->get()) {
            throw new \RuntimeException('操作正在进行中，请稍后重试');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    /**
     * Cache result for idempotency
     */
    protected function cacheIdempotencyResult(
        int $characterId,
        string $idempotencyKey,
        string $operation,
        array $result,
    ): void {
        $cacheKey = $this->getIdempotencyCacheKey($characterId, $idempotencyKey, $operation);
        Cache::put($cacheKey, $result, $this->getIdempotencyTtlSeconds());
    }

    /**
     * Get cached idempotency result
     */
    protected function getIdempotencyResult(int $characterId, string $idempotencyKey, string $operation): ?array
    {
        $cacheKey = $this->getIdempotencyCacheKey($characterId, $idempotencyKey, $operation);
        $cached = Cache::get($cacheKey);

        // Return null for 'processing' state or non-array values
        if (! is_array($cached)) {
            return null;
        }

        // Don't return 'processing' marker as a result
        if (isset($cached['__processing__'])) {
            return null;
        }

        return $cached;
    }

    /**
     * Cleanup a single idempotency key
     */
    private function cleanupIdempotencyKey(string $cacheKey): void
    {
        Redis::del($cacheKey);
        unset($this->activeIdempotencyKeys[$cacheKey]);
    }

    /**
     * Cleanup all active idempotency keys (called on error)
     */
    private function cleanupActiveIdempotencyKeys(): void
    {
        foreach ($this->activeIdempotencyKeys as $cacheKey) {
            Redis::del($cacheKey);
        }
        $this->activeIdempotencyKeys = [];
    }

    protected function getIdempotencyCacheKey(int $characterId, string $idempotencyKey, string $operation): string
    {
        return 'shop:idem:' . $characterId . ':' . $operation . ':' . $idempotencyKey;
    }

    /**
     * TTL for idempotency result cache (24 hours)
     */
    protected function getIdempotencyTtlSeconds(): int
    {
        return 86400;
    }

    /**
     * TTL for processing lock (shorter, just enough to prevent races during execution)
     */
    private function getProcessingTtlSeconds(): int
    {
        return 30; // 30 seconds - enough for one operation to complete
    }
}
