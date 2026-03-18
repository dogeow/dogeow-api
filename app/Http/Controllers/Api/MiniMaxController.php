<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MiniMaxController extends Controller
{
    public function __construct()
    {
        // MiniMax 国内版 API
    }

    /**
     * 获取 MiniMax 订阅用量信息
     * GET /api/minimax/subscription
     */
    public function subscription(): JsonResponse
    {
        $apiKey = config('services.minimax.api_key');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax API Key 未配置',
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->get('https://www.minimaxi.com/v1/api/openplatform/coding_plan/remains');

            if ($response->failed()) {
                Log::warning('[MiniMax] 订阅信息请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '请求 MiniMax API 失败: ' . $response->status(),
                    'data' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('[MiniMax] 订阅信息异常', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '获取订阅信息失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取 MiniMax 套餐到期时间
     * GET /api/minimax/subscription-detail
     */
    public function subscriptionDetail(): JsonResponse
    {
        $apiKey = config('services.minimax.api_key');
        $groupId = config('services.minimax.group_id');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax API Key 未配置',
            ], 500);
        }

        if (empty($groupId)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax Group ID 未配置，请在 .env 中设置 MINIMAX_GROUP_ID',
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->get('https://www.minimaxi.com/v1/api/openplatform/charge/combo/cycle_audio_resource_package', [
                'biz_line' => 2,
                'cycle_type' => 1,
                'resource_package_type' => 7,
                'GroupId' => $groupId,
            ]);

            if ($response->failed()) {
                Log::warning('[MiniMax] 套餐详情请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '请求 MiniMax API 失败: ' . $response->status(),
                    'data' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('[MiniMax] 套餐详情异常', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '获取套餐详情失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取 MiniMax Token 消耗账单
     * GET /api/minimax/billing
     */
    public function billing(): JsonResponse
    {
        $apiKey = config('services.minimax.api_key');
        $groupId = config('services.minimax.group_id');

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax API Key 未配置',
            ], 500);
        }

        if (empty($groupId)) {
            return response()->json([
                'success' => false,
                'message' => 'MiniMax Group ID 未配置，请在 .env 中设置 MINIMAX_GROUP_ID',
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(15)->get('https://www.minimaxi.com/account/amount', [
                'page' => 1,
                'limit' => 100,
                'aggregate' => false,
                'GroupId' => $groupId,
            ]);

            if ($response->failed()) {
                Log::warning('[MiniMax] 账单请求失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => '请求 MiniMax API 失败: ' . $response->status(),
                    'data' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('[MiniMax] 账单异常', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => '获取账单信息失败: ' . $e->getMessage(),
            ], 500);
        }
    }
}
