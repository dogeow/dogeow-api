<?php

namespace App\Http\Controllers;

use App\Services\Web\WebPageService;
use App\Services\Cache\CacheService;
use Illuminate\Http\Request;

class TitleController extends Controller
{
    public function __construct(
        private readonly WebPageService $webPageService,
        private readonly CacheService $cacheService
    ) {}

    public function fetch(Request $request)
    {
        $url = $request->query('url');
        if (empty($url)) {
            return $this->error('缺少url参数', [], 400);
        }

        // 先检查缓存
        $cachedData = $this->cacheService->get($url);
        if ($cachedData !== null) {
            // 如果缓存存在错误数据，返回错误响应
            if (!empty($cachedData['error'])) {
                $statusCode = $cachedData['status_code'] ?? 500;
                $details = is_array($cachedData['details'] ?? null)
                    ? $cachedData['details']
                    : [ 'details' => $cachedData['details'] ?? '' ];
                return $this->error($cachedData['error'] ?? '请求失败', $details, $statusCode);
            }
            // 如果缓存存在成功数据，返回成功响应
            return $this->success($cachedData);
        }

        // 缓存不存在，获取新数据
        try {
            $data = $this->webPageService->fetchContent($url);
            $this->cacheService->putSuccess($url, $data);
            return $this->success($data);
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $errorData = [
                'error' => '请求异常',
                'details' => [ 'message' => $errorMessage ],
                'status_code' => 500
            ];
            $this->cacheService->putError($url, $errorData);
            return $this->error('请求异常', ['details' => $errorMessage], 500);
        }
    }
}