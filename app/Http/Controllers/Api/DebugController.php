<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DebugController extends Controller
{
    /**
     * 记录前端错误日志
     */
    public function logError(Request $request)
    {
        $userId = Auth::id() ?? 'guest';
        
        // 验证请求数据
        $validated = $request->validate([
            'error_type' => 'required|string|max:100',
            'error_message' => 'required|string|max:1000',
            'error_details' => 'nullable|array',
            'user_agent' => 'nullable|string|max:1000',
            'timestamp' => 'nullable|string',
            'url' => 'nullable|string|max:1000',
        ]);
        
        // 构建日志信息
        $logData = [
            'user_id' => $userId,
            'error_type' => $validated['error_type'],
            'error_message' => $validated['error_message'],
            'error_details' => $validated['error_details'] ?? [],
            'user_agent' => $validated['user_agent'] ?? $request->header('User-Agent'),
            'timestamp' => $validated['timestamp'] ?? now()->toISOString(),
            'url' => $validated['url'] ?? $request->header('Referer'),
            'ip' => $request->ip(),
        ];
        
        // 使用自定义通道记录特定类型的错误日志
        if (str_contains($validated['error_type'], 'upload') || 
            str_contains($validated['error_type'], 'image') || 
            str_contains($validated['error_type'], 'canvas')) {
            // 图片上传相关错误使用单独的日志文件
            Log::channel('image_upload')->error('前端图片上传错误', $logData);
        } else {
            // 其他错误记录到默认日志
            Log::error('前端错误日志', $logData);
        }
        
        return response()->json([
            'message' => '错误日志已记录',
            'status' => 'success'
        ]);
    }
} 