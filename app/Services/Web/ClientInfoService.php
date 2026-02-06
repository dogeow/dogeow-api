<?php

namespace App\Services\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClientInfoService
{
    /**
     * 获取客户端基本信息（IP和User-Agent）
     */
    public function getBasicInfo(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
    }

    /**
     * 获取地理位置信息
     */
    public function getLocationInfo(string $ip): array
    {
        try {
            $ipInfo = Http::timeout(10)
                ->get("http://ip-api.com/json/{$ip}?lang=zh-CN")
                ->json();

            return [
                'location' => [
                    'country' => $ipInfo['country'] ?? null,
                    'region' => $ipInfo['regionName'] ?? null,
                    'city' => $ipInfo['city'] ?? null,
                    'isp' => $ipInfo['isp'] ?? null,
                    'timezone' => $ipInfo['timezone'] ?? null,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch location info', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return [
                'location' => [
                    'country' => null,
                    'region' => null,
                    'city' => null,
                    'isp' => null,
                    'timezone' => null,
                ],
                'error' => '地理位置信息获取失败'
            ];
        }
    }

    /**
     * 获取完整客户端信息
     */
    public function getClientInfo(Request $request): array
    {
        $ip = $request->ip();
        $basicInfo = $this->getBasicInfo($request);
        $locationInfo = $this->getLocationInfo($ip);

        return [
            'ip' => $basicInfo['ip'],
            'user_agent' => $basicInfo['user_agent'],
            'location' => $locationInfo['location'] ?? [],
        ];
    }
}
