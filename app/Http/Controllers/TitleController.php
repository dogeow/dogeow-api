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
            return response()->json(['title' => $title]);
        } catch (\Exception $e) {
            return response()->json(['error' => '请求异常'], 500);
        }
    }
} 