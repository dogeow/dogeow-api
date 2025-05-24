<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ClientInfoController extends Controller
{
    public function getClientInfo(Request $request)
    {
        $ip = $request->ip();
        $ipInfo = Http::get("http://ip-api.com/json/{$ip}")->json();

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