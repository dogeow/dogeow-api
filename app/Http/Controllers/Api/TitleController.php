<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        if (!$url) {
            return $this->error('缺少url参数', [], 400);
        }

        // 先检查缓存
        $cachedData = $this->cacheService->get($url);
        if ($cachedData !== null) {
            // 如果缓存存在错误数据，返回错误响应
            if (isset($cachedData['error'])) {
                $statusCode = $cachedData['status_code'] ?? 500;
                return $this->error($cachedData['error'] ?? '请求失败', $cachedData['details'] ?? [], $statusCode);
            }
            // 如果缓存存在成功数据，返回成功响应
            return $this->success($cachedData);
        }

        // 缓存不存在，获取新数据
        try {
            $data = $this->webPageService->fetchContent($url);
            $this->cacheService->putSuccess($url, $data);
            return $this->success($data);
        } catch (\Exception $e) {
            $errorData = [
                'error' => '请求异常',
                'details' => $e->getMessage(),
                'status_code' => 500
            ];
            $this->cacheService->putError($url, $errorData);
            return $this->error('请求异常', ['details' => $e->getMessage()], 500);
        }
    }
}
