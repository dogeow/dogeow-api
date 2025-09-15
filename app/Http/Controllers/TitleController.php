<?php

namespace App\Http\Controllers;

use App\Services\WebPageService;
use App\Services\CacheService;
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
            return response()->json(['error' => '缺少url参数'], 400);
        }

        try {
            $data = $this->webPageService->fetchContent($url);
            
            return response()->json($data);
        } catch (\Exception $e) {
            $errorData = [
                'error' => '请求异常',
                'details' => $e->getMessage(),
                'status_code' => 500
            ];
            $this->cacheService->putError($url, $errorData);
            return response()->json($errorData, 500);
        }
    }
}