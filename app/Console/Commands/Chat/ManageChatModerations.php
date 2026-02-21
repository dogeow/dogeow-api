<?php

namespace App\Console\Commands\Chat;

use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ManageChatModerations extends Command
{
    /**
     * 命令的名称和签名
     *
     * @var string
     */
    protected $signature = 'chat:moderation 
                            {action : 要执行的操作（list, unmute, unban, cleanup）}
                            {--user= : 用户ID或邮箱}
                            {--room= : 房间ID或名称}
                            {--all : 作用于所有用户/房间}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '管理聊天室管控操作（静音/封禁/解封/取消静音）';

    /**
     * 执行控制台命令
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'list':
                return $this->listModerations();
            case 'unmute':
                return $this->unmuteUser();
            case 'unban':
                return $this->unbanUser();
            case 'cleanup':
                return $this->cleanupExpiredModerations();
            default:
                $this->error("未知操作：{$action}");
                $this->info('可用操作: list, unmute, unban, cleanup');

                return Command::FAILURE;
        }
    }

    /**
     * 列出当前的管控
     */
    private function listModerations(): int
    {
        $this->info('当前聊天室管控列表:');
        $this->newLine();

        // 获取被静音的用户
        $mutedUsers = ChatRoomUser::where('is_muted', true)
            ->with(['user:id,name,email', 'room:id,name', 'mutedByUser:id,name'])
            ->get();

        if ($mutedUsers->isNotEmpty()) {
            $this->info('🔇 已被静音的用户:');
            $muteData = [];
            foreach ($mutedUsers as $roomUser) {
                $muteData[] = [
                    '用户' => $roomUser->user->name . ' (' . $roomUser->user->email . ')',
                    '房间' => $roomUser->room->name,
                    '操作人' => $roomUser->mutedByUser->name ?? '系统',
                    '截止时间' => $roomUser->muted_until ? $roomUser->muted_until->format('Y-m-d H:i:s') : '永久',
                    '状态' => $roomUser->muted_until && $roomUser->muted_until->isPast() ? '已过期' : '生效中',
                ];
            }
            $this->table(['用户', '房间', '操作人', '截止时间', '状态'], $muteData);
        }

        // 获取被封禁的用户
        $bannedUsers = ChatRoomUser::where('is_banned', true)
            ->with(['user:id,name,email', 'room:id,name', 'bannedByUser:id,name'])
            ->get();

        if ($bannedUsers->isNotEmpty()) {
            $this->newLine();
            $this->info('🚫 已被封禁的用户:');
            $banData = [];
            foreach ($bannedUsers as $roomUser) {
                $banData[] = [
                    '用户' => $roomUser->user->name . ' (' . $roomUser->user->email . ')',
                    '房间' => $roomUser->room->name,
                    '操作人' => $roomUser->bannedByUser->name ?? '系统',
                    '截止时间' => $roomUser->banned_until ? $roomUser->banned_until->format('Y-m-d H:i:s') : '永久',
                    '状态' => $roomUser->banned_until && $roomUser->banned_until->isPast() ? '已过期' : '生效中',
                ];
            }
            $this->table(['用户', '房间', '操作人', '截止时间', '状态'], $banData);
        }

        if ($mutedUsers->isEmpty() && $bannedUsers->isEmpty()) {
            $this->info('没有发现活跃的管控。');
        }

        return Command::SUCCESS;
    }

    /**
     * 取消用户静音
     */
    private function unmuteUser(): int
    {
        $userIdentifier = $this->option('user');
        $roomIdentifier = $this->option('room');
        $all = $this->option('all');

        if (! $all && (! $userIdentifier || ! $roomIdentifier)) {
            $this->error('请指定 --user 和 --room，或者使用 --all 取消全部静音');

            return Command::FAILURE;
        }

        if ($all) {
            $mutedUsers = ChatRoomUser::where('is_muted', true)->get();
            $count = $mutedUsers->count();

            foreach ($mutedUsers as $roomUser) {
                $roomUser->unmute();
            }

            $this->info("已取消全部房间共 {$count} 个用户的静音。");

            return Command::SUCCESS;
        }

        // 查找用户
        $user = $this->findUser($userIdentifier);
        if (! $user) {
            return Command::FAILURE;
        }

        // 查找房间
        $room = $this->findRoom($roomIdentifier);
        if (! $room) {
            return Command::FAILURE;
        }

        // 查找房间与用户关联关系
        $roomUser = ChatRoomUser::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $roomUser) {
            $this->error("用户 {$user->name} 不在房间 {$room->name} 中。");

            return Command::FAILURE;
        }

        if (! $roomUser->is_muted) {
            $this->info("用户 {$user->name} 在房间 {$room->name} 没有被静音。");

            return Command::SUCCESS;
        }

        $roomUser->unmute();
        $this->info("已成功取消用户 {$user->name} 在房间 {$room->name} 的静音。");

        return Command::SUCCESS;
    }

    /**
     * 解除封禁用户
     */
    private function unbanUser(): int
    {
        $userIdentifier = $this->option('user');
        $roomIdentifier = $this->option('room');
        $all = $this->option('all');

        if (! $all && (! $userIdentifier || ! $roomIdentifier)) {
            $this->error('请指定 --user 和 --room，或者使用 --all 解除全部封禁');

            return Command::FAILURE;
        }

        if ($all) {
            $bannedUsers = ChatRoomUser::where('is_banned', true)->get();
            $count = $bannedUsers->count();

            foreach ($bannedUsers as $roomUser) {
                $roomUser->unban();
            }

            $this->info("已解除全部房间共 {$count} 个用户的封禁。");

            return Command::SUCCESS;
        }

        // 查找用户
        $user = $this->findUser($userIdentifier);
        if (! $user) {
            return Command::FAILURE;
        }

        // 查找房间
        $room = $this->findRoom($roomIdentifier);
        if (! $room) {
            return Command::FAILURE;
        }

        // 查找房间与用户关联关系
        $roomUser = ChatRoomUser::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $roomUser) {
            $this->error("用户 {$user->name} 不在房间 {$room->name} 中。");

            return Command::FAILURE;
        }

        if (! $roomUser->is_banned) {
            $this->info("用户 {$user->name} 在房间 {$room->name} 没有被封禁。");

            return Command::SUCCESS;
        }

        $roomUser->unban();
        $this->info("已成功解除用户 {$user->name} 在房间 {$room->name} 的封禁。");

        return Command::SUCCESS;
    }

    /**
     * 清理已过期的管控
     */
    private function cleanupExpiredModerations(): int
    {
        $now = Carbon::now();

        // 清理已过期的静音
        $expiredMutes = ChatRoomUser::where('is_muted', true)
            ->where('muted_until', '<', $now)
            ->whereNotNull('muted_until')
            ->get();

        foreach ($expiredMutes as $roomUser) {
            $roomUser->unmute();
        }

        // 清理已过期的封禁
        $expiredBans = ChatRoomUser::where('is_banned', true)
            ->where('banned_until', '<', $now)
            ->whereNotNull('banned_until')
            ->get();

        foreach ($expiredBans as $roomUser) {
            $roomUser->unban();
        }

        $totalCleaned = $expiredMutes->count() + $expiredBans->count();
        $this->info("清理了 {$totalCleaned} 条已过期的管控（静音：{$expiredMutes->count()}，封禁：{$expiredBans->count()}）");

        return Command::SUCCESS;
    }

    /**
     * 通过ID或邮箱查找用户
     */
    private function findUser(string $identifier): ?User
    {
        $user = null;

        if (is_numeric($identifier)) {
            $user = User::find($identifier);
        } else {
            $user = User::where('email', $identifier)->first();
        }

        if (! $user) {
            $this->error("未找到用户：{$identifier}");

            return null;
        }

        return $user;
    }

    /**
     * 通过ID或名称查找房间
     */
    private function findRoom(string $identifier): ?ChatRoom
    {
        $room = null;

        if (is_numeric($identifier)) {
            $room = ChatRoom::find($identifier);
        } else {
            $room = ChatRoom::where('name', $identifier)->first();
        }

        if (! $room) {
            $this->error("未找到房间：{$identifier}");

            return null;
        }

        return $room;
    }
}
