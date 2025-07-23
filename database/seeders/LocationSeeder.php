<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\User;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 获取第一个用户，如果没有则创建一个
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]);
        }

        // 创建区域：老家
        $homeArea = Area::create([
            'name' => '老家',
            'user_id' => $user->id,
        ]);

        // 创建房间
        $rooms = [
            '姐姐房间',
            '卧室',
            '书房',
            '一楼大厅',
            '二楼大厅',
            '三楼机房',
        ];

        foreach ($rooms as $roomName) {
            $room = Room::create([
                'name' => $roomName,
                'area_id' => $homeArea->id,
                'user_id' => $user->id,
            ]);

            // 为每个房间创建一些具体位置
            $spots = [];
            switch ($roomName) {
                case '姐姐房间':
                    $spots = ['床头柜', '衣柜', '书桌'];
                    break;
                case '卧室':
                    $spots = ['床头', '梳妆台', '衣柜'];
                    break;
                case '书房':
                    $spots = ['书架', '办公桌', '储物柜'];
                    break;
                case '一楼大厅':
                    $spots = ['电视柜', '茶几', '沙发'];
                    break;
                case '二楼大厅':
                    $spots = ['休息区', '储物间'];
                    break;
                case '三楼机房':
                    $spots = ['服务器机柜', '工作台', '储物架'];
                    break;
            }

            foreach ($spots as $spotName) {
                Spot::create([
                    'name' => $spotName,
                    'room_id' => $room->id,
                    'user_id' => $user->id,
                ]);
            }
        }

        $this->command->info('位置数据创建完成！');
    }
}