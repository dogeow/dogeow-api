<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            $token = $user->createToken('auth_token')->plainTextToken;
            
            return response()->json([
                'user' => $user,
                'token' => $token
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['提供的凭证不正确。'],
        ]);
    }

    public function logout(Request $request)
    {
        // 删除当前使用的令牌
        $request->user()->currentAccessToken()->delete();
        
        return response()->json(['message' => '已成功登出']);
    }

    public function user(Request $request)
    {
        return $request->user();
    }
} 