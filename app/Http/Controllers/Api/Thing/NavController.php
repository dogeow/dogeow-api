<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NavController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return response()->json(['message' => '导航功能正在开发中']);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        return response()->json(['message' => '导航功能正在开发中']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return response()->json(['message' => '导航功能正在开发中']);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        return response()->json(['message' => '导航功能正在开发中']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return response()->json(['message' => '导航功能正在开发中']);
    }

    /**
     * Get navigation categories.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function categories()
    {
        return response()->json(['message' => '导航分类功能正在开发中']);
    }
}
