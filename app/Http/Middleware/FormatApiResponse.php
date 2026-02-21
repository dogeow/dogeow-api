<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FormatApiResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 仅格式化 API 路径下的 JSON 响应
        if (
            ! $request->is('api/*') ||
            ! $response instanceof JsonResponse
        ) {
            return $response;
        }

        $data = $response->getData(true);
        $statusCode = $response->getStatusCode();

        // 已含有标准格式字段，跳过格式化
        if (array_key_exists('success', $data) || array_key_exists('message', $data)) {
            return $response;
        }

        return response()->json(
            $this->formatResponse($data, $statusCode),
            $statusCode
        );
    }

    /**
     * 标准化响应结构
     */
    private function formatResponse(array $data, int $statusCode): array
    {
        $success = $statusCode >= 200 && $statusCode < 300;

        return [
            'success' => $success,
            'message' => $this->getDefaultMessage($statusCode),
            $success ? 'data' : 'errors' => $data,
        ];
    }

    /**
     * 获取响应默认消息
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
