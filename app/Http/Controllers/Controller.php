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
     * 兼容旧的 fail 响应方法
     */
    protected function fail(string $message, array $errors = [], int $code = 422): JsonResponse
    {
        return $this->error($message, $errors, $code);
    }


    /**
     * 获取当前认证用户ID
     */
    protected function getCurrentUserId(): int
    {
        return auth()->id();
    }

}
