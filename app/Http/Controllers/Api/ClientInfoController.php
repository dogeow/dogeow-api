<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClientInfoController extends Controller
{
    /**
     * 获取客户端基本信息（IP和User-Agent），立即返回
     */
    public function getBasicInfo(Request $request)
    {
        return response()->json([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * 获取地理位置信息，可能需要较长时间
     */
    public function getLocationInfo(Request $request)
    {
        $ip = $request->ip();
        
        try {
            $ipInfo = Http::timeout(10)->get("http://ip-api.com/json/{$ip}?lang=zh-CN")->json();

            return response()->json([
                'location' => [
                    'country' => $ipInfo['country'] ?? null,
                    'region' => $ipInfo['regionName'] ?? null,
                    'city' => $ipInfo['city'] ?? null,
                    'isp' => $ipInfo['isp'] ?? null,
                    'timezone' => $ipInfo['timezone'] ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'location' => [
                    'country' => null,
                    'region' => null,
                    'city' => null,
                    'isp' => null,
                    'timezone' => null,
                ],
                'error' => '地理位置信息获取失败'
            ], 500);
        }
    }

    /**
     * 获取完整客户端信息（保持向后兼容）
     */
    public function getClientInfo(Request $request)
    {
        $ip = $request->ip();
        $ipInfo = Http::get("http://ip-api.com/json/{$ip}?lang=zh-CN")->json();

        return response()->json([
            'ip' => $ip,
            'user_agent' => $request->userAgent(),
            'location' => [
                'country' => $ipInfo['country'] ?? null,
                'region' => $ipInfo['regionName'] ?? null,
                'city' => $ipInfo['city'] ?? null,
                'isp' => $ipInfo['isp'] ?? null,
                'timezone' => $ipInfo['timezone'] ?? null,
            ]
        ]);
    }
} 