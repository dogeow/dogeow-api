<?php

namespace App\Console\Commands;

use App\Models\ChatRoomUser;
use App\Models\User;
use App\Models\ChatRoom;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ManageChatModerations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:moderation 
                            {action : Action to perform (list, unmute, unban, cleanup)}
                            {--user= : User ID or email}
                            {--room= : Room ID or name}
                            {--all : Apply to all users/rooms}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage chat room moderations (mute/ban/unban/unmute)';

    /**
     * Execute the console command.
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
                $this->error("Unknown action: {$action}");
                $this->info("Available actions: list, unmute, unban, cleanup");
                return Command::FAILURE;
        }
    }

    /**
     * List current moderations
     */
    private function listModerations(): int
    {
        $this->info('Current Chat Moderations:');
        $this->newLine();

        // Get muted users
        $mutedUsers = ChatRoomUser::where('is_muted', true)
            ->with(['user:id,name,email', 'room:id,name', 'mutedByUser:id,name'])
            ->get();

        if ($mutedUsers->isNotEmpty()) {
            $this->info('🔇 MUTED USERS:');
            $muteData = [];
            foreach ($mutedUsers as $roomUser) {
                $muteData[] = [
                    'User' => $roomUser->user->name . ' (' . $roomUser->user->email . ')',
                    'Room' => $roomUser->room->name,
                    'Muted By' => $roomUser->mutedByUser->name ?? 'System',
                    'Until' => $roomUser->muted_until ? $roomUser->muted_until->format('Y-m-d H:i:s') : 'Permanent',
                    'Status' => $roomUser->muted_until && $roomUser->muted_until->isPast() ? 'EXPIRED' : 'ACTIVE'
                ];
            }
            $this->table(['User', 'Room', 'Muted By', 'Until', 'Status'], $muteData);
        }

        // Get banned users
        $bannedUsers = ChatRoomUser::where('is_banned', true)
            ->with(['user:id,name,email', 'room:id,name', 'bannedByUser:id,name'])
            ->get();

        if ($bannedUsers->isNotEmpty()) {
            $this->newLine();
            $this->info('🚫 BANNED USERS:');
            $banData = [];
            foreach ($bannedUsers as $roomUser) {
                $banData[] = [
                    'User' => $roomUser->user->name . ' (' . $roomUser->user->email . ')',
                    'Room' => $roomUser->room->name,
                    'Banned By' => $roomUser->bannedByUser->name ?? 'System',
                    'Until' => $roomUser->banned_until ? $roomUser->banned_until->format('Y-m-d H:i:s') : 'Permanent',
                    'Status' => $roomUser->banned_until && $roomUser->banned_until->isPast() ? 'EXPIRED' : 'ACTIVE'
                ];
            }
            $this->table(['User', 'Room', 'Banned By', 'Until', 'Status'], $banData);
        }

        if ($mutedUsers->isEmpty() && $bannedUsers->isEmpty()) {
            $this->info('No active moderations found.');
        }

        return Command::SUCCESS;
    }

    /**
     * Unmute a user
     */
    private function unmuteUser(): int
    {
        $userIdentifier = $this->option('user');
        $roomIdentifier = $this->option('room');
        $all = $this->option('all');

        if (!$all && (!$userIdentifier || !$roomIdentifier)) {
            $this->error('Please specify --user and --room options, or use --all to unmute all users');
            return Command::FAILURE;
        }

        if ($all) {
            $mutedUsers = ChatRoomUser::where('is_muted', true)->get();
            $count = $mutedUsers->count();
            
            foreach ($mutedUsers as $roomUser) {
                $roomUser->unmute();
            }
            
            $this->info("Unmuted {$count} users from all rooms.");
            return Command::SUCCESS;
        }

        // Find user
        $user = $this->findUser($userIdentifier);
        if (!$user) {
            return Command::FAILURE;
        }

        // Find room
        $room = $this->findRoom($roomIdentifier);
        if (!$room) {
            return Command::FAILURE;
        }

        // Find room user relationship
        $roomUser = ChatRoomUser::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$roomUser) {
            $this->error("User {$user->name} is not in room {$room->name}");
            return Command::FAILURE;
        }

        if (!$roomUser->is_muted) {
            $this->info("User {$user->name} is not muted in room {$room->name}");
            return Command::SUCCESS;
        }

        $roomUser->unmute();
        $this->info("Successfully unmuted {$user->name} in room {$room->name}");

        return Command::SUCCESS;
    }

    /**
     * Unban a user
     */
    private function unbanUser(): int
    {
        $userIdentifier = $this->option('user');
        $roomIdentifier = $this->option('room');
        $all = $this->option('all');

        if (!$all && (!$userIdentifier || !$roomIdentifier)) {
            $this->error('Please specify --user and --room options, or use --all to unban all users');
            return Command::FAILURE;
        }

        if ($all) {
            $bannedUsers = ChatRoomUser::where('is_banned', true)->get();
            $count = $bannedUsers->count();
            
            foreach ($bannedUsers as $roomUser) {
                $roomUser->unban();
            }
            
            $this->info("Unbanned {$count} users from all rooms.");
            return Command::SUCCESS;
        }

        // Find user
        $user = $this->findUser($userIdentifier);
        if (!$user) {
            return Command::FAILURE;
        }

        // Find room
        $room = $this->findRoom($roomIdentifier);
        if (!$room) {
            return Command::FAILURE;
        }

        // Find room user relationship
        $roomUser = ChatRoomUser::where('room_id', $room->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$roomUser) {
            $this->error("User {$user->name} is not in room {$room->name}");
            return Command::FAILURE;
        }

        if (!$roomUser->is_banned) {
            $this->info("User {$user->name} is not banned in room {$room->name}");
            return Command::SUCCESS;
        }

        $roomUser->unban();
        $this->info("Successfully unbanned {$user->name} in room {$room->name}");

        return Command::SUCCESS;
    }

    /**
     * Clean up expired moderations
     */
    private function cleanupExpiredModerations(): int
    {
        $now = Carbon::now();

        // Clean up expired mutes
        $expiredMutes = ChatRoomUser::where('is_muted', true)
            ->where('muted_until', '<', $now)
            ->whereNotNull('muted_until')
            ->get();

        foreach ($expiredMutes as $roomUser) {
            $roomUser->unmute();
        }

        // Clean up expired bans
        $expiredBans = ChatRoomUser::where('is_banned', true)
            ->where('banned_until', '<', $now)
            ->whereNotNull('banned_until')
            ->get();

        foreach ($expiredBans as $roomUser) {
            $roomUser->unban();
        }

        $totalCleaned = $expiredMutes->count() + $expiredBans->count();
        $this->info("Cleaned up {$totalCleaned} expired moderations ({$expiredMutes->count()} mutes, {$expiredBans->count()} bans)");

        return Command::SUCCESS;
    }

    /**
     * Find user by ID or email
     */
    private function findUser(string $identifier): ?User
    {
        $user = null;

        if (is_numeric($identifier)) {
            $user = User::find($identifier);
        } else {
            $user = User::where('email', $identifier)->first();
        }

        if (!$user) {
            $this->error("User not found: {$identifier}");
            return null;
        }

        return $user;
    }

    /**
     * Find room by ID or name
     */
    private function findRoom(string $identifier): ?ChatRoom
    {
        $room = null;

        if (is_numeric($identifier)) {
            $room = ChatRoom::find($identifier);
        } else {
            $room = ChatRoom::where('name', $identifier)->first();
        }

        if (!$room) {
            $this->error("Room not found: {$identifier}");
            return null;
        }

        return $room;
    }
}