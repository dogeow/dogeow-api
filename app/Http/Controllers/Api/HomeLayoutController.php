<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateHomeLayoutRequest;
use App\Models\UserHomeLayout;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class HomeLayoutController extends Controller
{
    /**
     * 获取用户首页布局配置
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();
        $layout = UserHomeLayout::firstOrCreate(
            ['user_id' => $user->id],
            ['layout' => ['tiles' => []]]
        );

        return response()->json($layout);
    }

    /**
     * 更新首页布局配置
     */
    public function update(UpdateHomeLayoutRequest $request): JsonResponse
    {
        $user = Auth::user();
        $layout = UserHomeLayout::firstOrCreate(
            ['user_id' => $user->id],
            ['layout' => ['tiles' => []]]
        );

        $validatedData = $request->validated();
        
        // 调试：记录接收到的数据
        \Log::info('Updating home layout', [
            'user_id' => $user->id,
            'received_data' => $validatedData,
        ]);
        
        // 确保 layout 字段正确设置
        $layout->layout = $validatedData['layout'];
        $saved = $layout->save();

        // 调试：记录保存结果
        \Log::info('Home layout saved', [
            'user_id' => $user->id,
            'saved' => $saved,
            'layout_data' => $layout->layout,
        ]);

        // 重新加载以确保返回最新数据
        $layout->refresh();

        return response()->json([
            'message' => '布局配置更新成功',
            'layout' => $layout,
        ]);
    }
}
