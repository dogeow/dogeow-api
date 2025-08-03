<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\CleanupDisconnectedChatUsers;
use App\Models\ChatRoom;
use App\Models\ChatRoomUser;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupDisconnectedChatUsersTest extends TestCase
{
    use RefreshDatabase;

    protected CleanupDisconnectedChatUsers $command;
    protected User $user1;
    protected User $user2;
    protected User $user3;
    protected ChatRoom $room1;
    protected ChatRoom $room2;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->command = new CleanupDisconnectedChatUsers();
        
        // Create test users
        $this->user1 = User::factory()->create(['name' => 'John Doe']);
        $this->user2 = User::factory()->create(['name' => 'Jane Smith']);
        $this->user3 = User::factory()->create(['name' => 'Bob Wilson']);
        
        // Create test rooms
        $this->room1 = ChatRoom::factory()->create(['name' => 'General Chat']);
        $this->room2 = ChatRoom::factory()->create(['name' => 'Support Room']);
    }

    public function test_cleanup_disconnected_users_successfully()
    {
        // Create online users with different last seen times
        $activeUser = ChatRoomUser::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(2), // Recently active
        ]);

        $inactiveUser = ChatRoomUser::factory()->create([
            'user_id' => $this->user2->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(10), // Inactive for 10 minutes
        ]);

        $veryInactiveUser = ChatRoomUser::factory()->create([
            'user_id' => $this->user3->id,
            'room_id' => $this->room2->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(15), // Inactive for 15 minutes
        ]);

        // Run the command
        $this->artisan('chat:cleanup-disconnected', ['--minutes' => 5])
            ->expectsOutput('Starting cleanup of users inactive for 5 minutes...')
            ->expectsOutput('Cleanup completed successfully. Marked 2 users as offline.')
            ->assertExitCode(0);

        // Verify that inactive users were marked as offline
        $this->assertDatabaseHas('chat_room_users', [
            'id' => $inactiveUser->id,
            'is_online' => false,
        ]);

        $this->assertDatabaseHas('chat_room_users', [
            'id' => $veryInactiveUser->id,
            'is_online' => false,
        ]);

        // Verify that active user remains online
        $this->assertDatabaseHas('chat_room_users', [
            'id' => $activeUser->id,
            'is_online' => true,
        ]);
    }

    public function test_cleanup_with_custom_inactive_minutes()
    {
        // Create users with different inactivity periods
        $user1 = ChatRoomUser::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(3), // Inactive for 3 minutes
        ]);

        $user2 = ChatRoomUser::factory()->create([
            'user_id' => $this->user2->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(8), // Inactive for 8 minutes
        ]);

        // Run cleanup with 5 minutes threshold
        $this->artisan('chat:cleanup-disconnected', ['--minutes' => 5])
            ->expectsOutput('Starting cleanup of users inactive for 5 minutes...')
            ->expectsOutput('Cleanup completed successfully. Marked 1 users as offline.')
            ->assertExitCode(0);

        // Verify only user2 was marked as offline
        $this->assertDatabaseHas('chat_room_users', [
            'id' => $user1->id,
            'is_online' => true,
        ]);

        $this->assertDatabaseHas('chat_room_users', [
            'id' => $user2->id,
            'is_online' => false,
        ]);
    }

    public function test_cleanup_with_no_inactive_users()
    {
        // Create only active users
        ChatRoomUser::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(2),
        ]);

        ChatRoomUser::factory()->create([
            'user_id' => $this->user2->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(1),
        ]);

        $this->artisan('chat:cleanup-disconnected', ['--minutes' => 5])
            ->expectsOutput('Starting cleanup of users inactive for 5 minutes...')
            ->expectsOutput('Cleanup completed successfully. Marked 0 users as offline.')
            ->assertExitCode(0);
    }

    public function test_cleanup_ignores_offline_users()
    {
        // Create offline users (should be ignored)
        ChatRoomUser::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $this->room1->id,
            'is_online' => false,
            'last_seen_at' => Carbon::now()->subMinutes(10),
        ]);

        // Create online inactive user
        $inactiveUser = ChatRoomUser::factory()->create([
            'user_id' => $this->user2->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(10),
        ]);

        $this->artisan('chat:cleanup-disconnected', ['--minutes' => 5])
            ->expectsOutput('Starting cleanup of users inactive for 5 minutes...')
            ->expectsOutput('Cleanup completed successfully. Marked 1 users as offline.')
            ->assertExitCode(0);

        // Verify only the online inactive user was processed
        $this->assertDatabaseHas('chat_room_users', [
            'id' => $inactiveUser->id,
            'is_online' => false,
        ]);
    }

    public function test_cleanup_with_default_minutes_option()
    {
        // Create inactive user
        $inactiveUser = ChatRoomUser::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(10),
        ]);

        // Run without specifying minutes (should use default 5)
        $this->artisan('chat:cleanup-disconnected')
            ->expectsOutput('Starting cleanup of users inactive for 5 minutes...')
            ->expectsOutput('Cleanup completed successfully. Marked 1 users as offline.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('chat_room_users', [
            'id' => $inactiveUser->id,
            'is_online' => false,
        ]);
    }

    public function test_cleanup_with_zero_minutes()
    {
        // Create users with different last seen times
        $user1 = ChatRoomUser::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(1),
        ]);

        $user2 = ChatRoomUser::factory()->create([
            'user_id' => $this->user2->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(5),
        ]);

        // Run with 0 minutes (should mark all online users as offline)
        $this->artisan('chat:cleanup-disconnected', ['--minutes' => 0])
            ->expectsOutput('Starting cleanup of users inactive for 0 minutes...')
            ->expectsOutput('Cleanup completed successfully. Marked 2 users as offline.')
            ->assertExitCode(0);

        // Verify both users were marked as offline
        $this->assertDatabaseHas('chat_room_users', [
            'id' => $user1->id,
            'is_online' => false,
        ]);

        $this->assertDatabaseHas('chat_room_users', [
            'id' => $user2->id,
            'is_online' => false,
        ]);
    }

    public function test_cleanup_with_very_high_minutes_threshold()
    {
        // Create users with different last seen times
        $user1 = ChatRoomUser::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(10),
        ]);

        $user2 = ChatRoomUser::factory()->create([
            'user_id' => $this->user2->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => Carbon::now()->subMinutes(20),
        ]);

        // Run with 30 minutes threshold (should not mark any users as offline)
        $this->artisan('chat:cleanup-disconnected', ['--minutes' => 30])
            ->expectsOutput('Starting cleanup of users inactive for 30 minutes...')
            ->expectsOutput('Cleanup completed successfully. Marked 0 users as offline.')
            ->assertExitCode(0);

        // Verify both users remain online
        $this->assertDatabaseHas('chat_room_users', [
            'id' => $user1->id,
            'is_online' => true,
        ]);

        $this->assertDatabaseHas('chat_room_users', [
            'id' => $user2->id,
            'is_online' => true,
        ]);
    }

    public function test_cleanup_with_null_last_seen_at()
    {
        // Create user with null last_seen_at (should NOT be considered inactive by scope)
        $user = ChatRoomUser::factory()->create([
            'user_id' => $this->user1->id,
            'room_id' => $this->room1->id,
            'is_online' => true,
            'last_seen_at' => null,
        ]);

        $this->artisan('chat:cleanup-disconnected', ['--minutes' => 5])
            ->expectsOutput('Starting cleanup of users inactive for 5 minutes...')
            ->expectsOutput('Cleanup completed successfully. Marked 0 users as offline.')
            ->assertExitCode(0);

        // Verify user remains online (null last_seen_at is not considered inactive by scope)
        $this->assertDatabaseHas('chat_room_users', [
            'id' => $user->id,
            'is_online' => true,
        ]);
    }
} 