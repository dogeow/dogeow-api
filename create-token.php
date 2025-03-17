<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

// 检查是否有用户
$user = User::first();
if ($user) {
    // 删除旧令牌
    $user->tokens()->delete();
    
    // 创建新令牌
    $token = $user->createToken('test-token');
    
    // 保存令牌到文件
    file_put_contents(storage_path('app/test-token.txt'), $token->plainTextToken);
    
    echo "已为用户 {$user->name} 创建测试令牌并保存到 storage/app/test-token.txt\n";
} else {
    echo "没有找到用户，请先创建用户\n";
} 