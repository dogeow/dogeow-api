<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'message' => '请求过于频繁，请稍后再试',
                'retry_after' => RateLimiter::availableIn($key)
            ], 429);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        // 添加限流头部信息
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($key, $maxAttempts));
        
        return $response;
    }
    
    /**
     * Resolve the request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        
        if ($user) {
            return 'rate_limit:user:' . $user->id;
        }
        
        return 'rate_limit:ip:' . $request->ip();
    }
} 