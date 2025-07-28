<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class WebSocketAuthMiddleware
{
    /**
     * Handle an incoming request for WebSocket authentication.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for token in query parameters (for WebSocket connections)
        $token = $request->query('token') ?? $request->bearerToken();
        
        if (!$token) {
            return response()->json(['error' => 'Unauthorized - No token provided'], 401);
        }

        // Validate the token using Sanctum
        $accessToken = PersonalAccessToken::findToken($token);
        
        if (!$accessToken || !$accessToken->tokenable) {
            return response()->json(['error' => 'Unauthorized - Invalid token'], 401);
        }

        // Check if token has expired
        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return response()->json(['error' => 'Unauthorized - Token expired'], 401);
        }

        // Set the authenticated user
        Auth::setUser($accessToken->tokenable);
        
        // Update last used timestamp
        $accessToken->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
