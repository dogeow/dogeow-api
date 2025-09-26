<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

abstract class Controller
{
    /**
     * 返回成功响应
     */
    protected function success(array $data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        $response = ['message' => $message];
        if (!empty($data)) {
            $response = array_merge($response, $data);
        }
        return response()->json($response, $code);
    }

    /**
     * 返回错误响应
     */
    protected function error(string $message, array $errors = [], int $code = 422): JsonResponse
    {
        $response = ['message' => $message];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $code);
    }

    /**
     * 返回分页响应
     */
    protected function paginated($data, string $message = 'Data retrieved successfully'): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'has_more_pages' => $data->hasMorePages(),
            ]
        ]);
    }

    /**
     * 获取当前认证用户ID
     */
    protected function getCurrentUserId(): int
    {
        return auth()->id();
    }

    /**
     * 验证分页参数
     */
    protected function getPaginationParams($request, int $defaultPerPage = 20, int $maxPerPage = 100): array
    {
        $perPage = (int) $request->get('per_page', $defaultPerPage);
        $perPage = max(1, min($perPage, $maxPerPage));
        $page = max(1, (int) $request->get('page', 1));
        return [$page, $perPage];
    }
}
