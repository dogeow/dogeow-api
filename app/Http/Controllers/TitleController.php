<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TitleController extends Controller
{
    public function fetch(Request $request)
    {
        $url = $request->query('url');
        if (!$url) {
            return response()->json(['error' => '缺少url参数'], 400);
        }

        try {
            $response = Http::timeout(5)->get($url);
            if (!$response->ok()) {
                return response()->json(['error' => '获取网页失败'], 500);
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
            return response()->json(['title' => $title, 'favicon' => $favicon]);
        } catch (\Exception $e) {
            return response()->json(['error' => '请求异常'], 500);
        }
    }
} 