<?php

namespace Tests\Unit\Models;

use App\Models\Chat\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_be_created_with_factory()
    {
        $user = User::factory()->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_user_fillable_attributes()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'is_admin' => false,
        ];

        $user = User::create($userData);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertFalse($user->is_admin);
    }

    public function test_user_hidden_attributes()
    {
        $user = User::factory()->create();

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function test_user_casts()
    {
        $user = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->assertTrue($user->is_admin);
        $this->assertIsBool($user->is_admin);
    }

    public function test_is_admin_method()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);

        $this->assertTrue($adminUser->isAdmin());
        $this->assertFalse($regularUser->isAdmin());
    }

    public function test_can_moderate_method_with_admin()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);
        $otherUser = User::factory()->create();
        $room = ChatRoom::factory()->create(['created_by' => $otherUser->id]);

        $this->assertTrue($adminUser->canModerate());
        $this->assertTrue($adminUser->canModerate($room));
    }

    public function test_can_moderate_method_with_room_creator()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $room = ChatRoom::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($user->canModerate($room));
    }

    public function test_can_moderate_method_with_non_creator_non_admin()
    {
        $user = User::factory()->create(['is_admin' => false]);
        $otherUser = User::factory()->create();
        $room = ChatRoom::factory()->create(['created_by' => $otherUser->id]);

        $this->assertFalse($user->canModerate($room));
    }

    public function test_has_role_method()
    {
        $adminUser = User::factory()->create(['is_admin' => true]);
        $regularUser = User::factory()->create(['is_admin' => false]);

        // Test admin role
        $this->assertTrue($adminUser->hasRole('admin'));
        $this->assertFalse($regularUser->hasRole('admin'));

        // Test moderator role (currently same as admin)
        $this->assertTrue($adminUser->hasRole('moderator'));
        $this->assertFalse($regularUser->hasRole('moderator'));

        // Test unknown role
        $this->assertFalse($adminUser->hasRole('unknown'));
        $this->assertFalse($regularUser->hasRole('unknown'));
    }

    public function test_user_can_create_api_tokens()
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token');

        $this->assertNotNull($token);
        $this->assertNotNull($token->plainTextToken);
    }

    public function test_user_relationships()
    {
        $user = User::factory()->create();

        // Test that user can have tokens
        $token = $user->tokens()->create([
            'name' => 'test-token',
            'token' => hash('sha256', 'test-token'),
            'abilities' => ['*'],
        ]);

        $this->assertCount(1, $user->tokens);
        $this->assertEquals($token->id, $user->tokens->first()->id);
    }

    public function test_user_email_verification()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $this->assertNull($user->email_verified_at);

        $user->markEmailAsVerified();

        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_user_password_hashing()
    {
        $user = User::factory()->create([
            'password' => 'plain-password',
        ]);

        $this->assertNotEquals('plain-password', $user->password);
        $this->assertTrue(password_verify('plain-password', $user->password));
    }
}
