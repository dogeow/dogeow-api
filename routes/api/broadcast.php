<?php

use Illuminate\Support\Facades\Route;

// 广播认证路由 - 支持公共和私有频道
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    $channelName = $request->input('channel_name');
    
    // 如果是公共频道（不以 private- 或 presence- 开头），允许访问
    if (!str_starts_with($channelName, 'private-') && !str_starts_with($channelName, 'presence-')) {
        return response()->json([]);
    }
    
    // 对于私有频道，需要认证
    if (!auth('sanctum')->check()) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    
    return response()->json(['auth' => 'success']);
});
