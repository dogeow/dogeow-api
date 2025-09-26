<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class FormatApiResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 只处理 JSON 响应
        if (!$response instanceof JsonResponse) {
            return $response;
        }

        // 只处理 API 路由
        if (!$request->is('api/*')) {
            return $response;
        }

        $data = $response->getData(true);
        $statusCode = $response->getStatusCode();

        // 如果响应已经有标准格式，直接返回
        if (isset($data['success']) || isset($data['message'])) {
            return $response;
        }

        // 格式化响应
        $formattedData = $this->formatResponse($data, $statusCode);
        
        return response()->json($formattedData, $statusCode);
    }

    /**
     * 格式化响应数据
     */
    private function formatResponse(array $data, int $statusCode): array
    {
        $isSuccess = $statusCode >= 200 && $statusCode < 300;
        
        $formatted = [
            'success' => $isSuccess,
            'message' => $this->getDefaultMessage($statusCode),
        ];

        if ($isSuccess) {
            $formatted['data'] = $data;
        } else {
            $formatted['errors'] = $data;
        }

        return $formatted;
    }

    /**
     * 获取默认消息
     */
    private function getDefaultMessage(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'Success',
            201 => 'Created successfully',
            204 => 'No content',
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            422 => 'Validation failed',
            429 => 'Too many requests',
            500 => 'Internal server error',
            default => 'Request processed',
        };
    }
}