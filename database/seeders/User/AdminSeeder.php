<?php

namespace Database\Seeders\User;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * 创建管理员账户
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'email_verified_at' => now(),
                'password' => Hash::make('123456'),
                'is_admin' => true,
                'remember_token' => Str::random(10),
            ]
        );
    }
}
