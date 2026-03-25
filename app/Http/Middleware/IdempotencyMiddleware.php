<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency middleware to prevent duplicate form submissions.
 *
 * Clients should send a unique X-Idempotency-Key header with POST/PUT/PATCH/DELETE requests.
 * If the same key is received again within 24 hours, the cached response is returned
 * instead of re-processing the request.
 *
 * Uses Redis SETNX (SET if Not eXists) pattern to atomically claim the idempotency slot
 * before processing, preventing race conditions where concurrent duplicate requests could
 * both pass the cache-check and be processed simultaneously.
 *
 * Idempotency keys are stored in Redis with a 24-hour TTL.
 */
class IdempotencyMiddleware
{
    private const IDEMPOTENCY_HEADER = 'X-Idempotency-Key';

    private const PROCESSING_SUFFIX = ':processing';

    private const RESPONSE_SUFFIX = ':response';

    private const IDEMPOTENCY_PREFIX = 'idempotency:';

    private const IDEMPOTENCY_TTL = 86400; // 24 hours in seconds

    private const PROCESSING_TTL = 60; // 60 seconds – max expected processing time

    /**
     * HTTP methods considered idempotent (can be safely cached).
     */
    private const CACHEABLE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only cache unsafe methods
        if (! in_array($request->method(), self::CACHEABLE_METHODS, true)) {
            return $next($request);
        }

        $idempotencyKey = $request->header(self::IDEMPOTENCY_HEADER);

        // If no idempotency key provided, pass through (idempotency is optional)
        if (empty($idempotencyKey)) {
            return $next($request);
        }

        // Validate key format (max 128 chars, alphanumeric + dash/underscore)
        if (! $this->isValidKey($idempotencyKey)) {
            return $this->invalidKeyResponse();
        }

        $cacheKey = $this->buildCacheKey($request, $idempotencyKey);
        $processingKey = $cacheKey . self::PROCESSING_SUFFIX;
        $responseKey = $cacheKey . self::RESPONSE_SUFFIX;

        // Step 1: Check if we already have a completed response cached
        $cachedResponse = $this->getCachedResponse($responseKey);
        if ($cachedResponse !== null) {
            return $this->buildCachedResponse($cachedResponse);
        }

        // Step 2: Atomically claim the processing slot using SETNX
        // This prevents the race condition where two concurrent requests with
        // the same idempotency key could both pass the cache check and be processed.
        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = Redis::connection();

        $claimed = $redis->setnx($processingKey, '1');

        if (! $claimed) {
            // Another request is already processing this idempotency key
            // Check once more if a response was completed while we were waiting
            $cachedResponse = $this->getCachedResponse($responseKey);
            if ($cachedResponse !== null) {
                return $this->buildCachedResponse($cachedResponse);
            }

            // Still processing – tell client to retry
            return $this->processingResponse();
        }

        // Set TTL on processing marker so it auto-releases if process crashes
        $redis->expire($processingKey, self::PROCESSING_TTL);

        try {
            // Step 3: Process the request
            /** @var \Symfony\Component\HttpFoundation\Response $response */
            $response = $next($request);

            // Step 4: Cache the response only for successful responses
            if ($response->isSuccessful() || $response->isRedirect()) {
                $this->cacheResponse($responseKey, $response);
            }

            return $response;
        } finally {
            // Step 5: Remove the processing marker (do not remove the response key –
            // it has its own TTL and must survive the processing slot being freed)
            $redis->del($processingKey);
        }
    }

    /**
     * Check if an idempotency key is valid.
     */
    private function isValidKey(string $key): bool
    {
        if (strlen($key) > 128) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9_-]+$/', $key) === 1;
    }

    /**
     * Build the Redis cache key for an idempotency key.
     */
    private function buildCacheKey(Request $request, string $idempotencyKey): string
    {
        // Include request path + method to prevent cross-endpoint collisions
        $requestIdentifier = md5($request->path() . ':' . $request->method());

        return self::IDEMPOTENCY_PREFIX . $requestIdentifier . ':' . $idempotencyKey;
    }

    /**
     * Get a cached response for an idempotency key.
     */
    private function getCachedResponse(string $responseKey): ?array
    {
        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = Redis::connection();

        $cached = $redis->get($responseKey);

        if ($cached === null || $cached === false) {
            return null;
        }

        $decoded = json_decode($cached, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Cache a response for an idempotency key.
     */
    private function cacheResponse(string $responseKey, Response $response): void
    {
        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = Redis::connection();

        $payload = json_encode([
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'content' => $response->getContent(),
        ]);

        $redis->setex($responseKey, self::IDEMPOTENCY_TTL, $payload);
    }

    /**
     * Build a Response from cached data.
     */
    private function buildCachedResponse(array $cached): Response
    {
        $response = new Response(
            $cached['content'] ?? '',
            $cached['status'] ?? 200,
            $cached['headers'] ?? []
        );

        // Mark as a cached (idempotent) response
        $response->headers->set('X-Idempotent-Replay', 'true');

        return $response;
    }

    /**
     * Return a "request is being processed" response.
     */
    private function processingResponse(): Response
    {
        return response()->json([
            'success' => false,
            'message' => '请求正在处理中，请稍后重试',
        ], 409);
    }

    /**
     * Return an error response for invalid idempotency keys.
     */
    private function invalidKeyResponse(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Invalid X-Idempotency-Key: must be 1-128 alphanumeric characters, dashes, or underscores',
        ], 400);
    }
}
