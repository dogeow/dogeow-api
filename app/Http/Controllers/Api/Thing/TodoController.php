<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TodoController extends Controller
{
    /**
     * 获取待办事项列表
     */
    public function index()
    {
        return response()->json([
            'message' => '待办事项功能正在开发中'
        ]);
    }

    /**
     * 存储新的待办事项
     */
    public function store(Request $request)
    {
        return response()->json([
            'message' => '待办事项功能正在开发中'
        ]);
    }

    /**
     * 显示指定的待办事项
     */
    public function show($id)
    {
        return response()->json([
            'message' => '待办事项功能正在开发中'
        ]);
    }

    /**
     * 更新指定的待办事项
     */
    public function update(Request $request, $id)
    {
        return response()->json([
            'message' => '待办事项功能正在开发中'
        ]);
    }

    /**
     * 删除指定的待办事项
     */
    public function destroy($id)
    {
        return response()->json([
            'message' => '待办事项功能正在开发中'
        ]);
    }
} 