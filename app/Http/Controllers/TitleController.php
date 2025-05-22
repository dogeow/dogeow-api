<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache; // Added

class TitleController extends Controller
{
    public function fetch(Request $request)
    {
        $url = $request->query('url');
        if (!$url) {
            return response()->json(['error' => '缺少url参数'], 400);
        }

        $cacheKey = 'title_favicon_' . md5($url);

        if (Cache::has($cacheKey)) {
            $cachedData = Cache::get($cacheKey);
            // Ensure errors are returned with the correct status code
            if (isset($cachedData['error'])) {
                return response()->json($cachedData, isset($cachedData['status_code']) ? $cachedData['status_code'] : 500);
            }
            return response()->json($cachedData);
        }

        try {
            $response = Http::timeout(5)->get($url);
            if (!$response->ok()) {
                $errorData = ['error' => '获取网页失败', 'details' => $response->status(), 'status_code' => 500];
                Cache::put($cacheKey, $errorData, now()->addMinutes(30)); // Cache error for 30 minutes
                return response()->json($errorData, 500);
            }
            $html = $response->body();
            preg_match('/<title>(.*?)<\/title>/is', $html, $matches);
            $title = $matches[1] ?? '';
            $favicon = '';
            if (preg_match('/<link[^>]+rel=[\'\"]?(?:shortcut )?icon[\'\"]?[^>]*>/i', $html, $iconTag)) {
                if (preg_match('/href=[\'\"]([^\'\"]+)[\'\"]/i', $iconTag[0], $hrefMatch)) {
                    $favicon = $hrefMatch[1];
                    if (!preg_match('/^https?:\/\//i', $favicon)) {
                        $parsed = parse_url($url);
                        $origin = $parsed['scheme'] . '://' . $parsed['host'];
                        if (str_starts_with($favicon, '/')) {
                            $favicon = $origin . $favicon;
                        } else {
                            $favicon = rtrim($origin . dirname($parsed['path']), '/') . '/' . $favicon;
                        }
                    }
                }
            }
            if (!$favicon) {
                $parsed = parse_url($url);
                $favicon = $parsed['scheme'] . '://' . $parsed['host'] . '/favicon.ico';
            }

            $dataToCache = ['title' => $title, 'favicon' => $favicon];
            Cache::put($cacheKey, $dataToCache, now()->addHours(24)); // Cache for 24 hours
            return response()->json($dataToCache);

        } catch (\Exception $e) {
            $errorData = ['error' => '请求异常', 'details' => $e->getMessage(), 'status_code' => 500];
            Cache::put($cacheKey, $errorData, now()->addMinutes(30)); // Cache error for 30 minutes
            return response()->json($errorData, 500);
        }
    }
}