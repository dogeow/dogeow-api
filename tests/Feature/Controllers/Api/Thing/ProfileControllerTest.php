<?php

namespace Tests\Feature\Controllers\Api\Thing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_edit_returns_profile_view()
    {
        $response = $this->get('/profile');

        $response->assertStatus(200);
        $response->assertViewIs('profile.edit');
        $response->assertViewHas('user', $this->user);
    }

    public function test_update_profile_with_valid_data()
    {
        $updateData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ];

        $response = $this->put('/profile', $updateData);

        $response->assertRedirect(route('profile.edit'));
        $response->assertSessionHas('status', 'profile-updated');

        $this->user->refresh();
        $this->assertEquals('Updated Name', $this->user->name);
        $this->assertEquals('updated@example.com', $this->user->email);
    }

    public function test_update_profile_with_email_change_resets_verification()
    {
        $this->user->update(['email_verified_at' => now()]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'newemail@example.com',
        ];

        $response = $this->put('/profile', $updateData);

        $response->assertRedirect(route('profile.edit'));

        $this->user->refresh();
        $this->assertNull($this->user->email_verified_at);
    }

    public function test_update_profile_without_email_change_keeps_verification()
    {
        $this->user->update(['email_verified_at' => now()]);

        $updateData = [
            'name' => 'Updated Name',
            'email' => $this->user->email, // 相同的邮箱
        ];

        $response = $this->put('/profile', $updateData);

        $response->assertRedirect(route('profile.edit'));

        $this->user->refresh();
        $this->assertNotNull($this->user->email_verified_at);
    }

    public function test_update_profile_with_invalid_data()
    {
        $updateData = [
            'name' => '',
            'email' => 'invalid-email',
        ];

        $response = $this->put('/profile', $updateData);

        $response->assertSessionHasErrors(['name', 'email']);
    }

    public function test_update_profile_with_duplicate_email()
    {
        $otherUser = User::factory()->create(['email' => 'existing@example.com']);

        $updateData = [
            'name' => 'Updated Name',
            'email' => 'existing@example.com',
        ];

        $response = $this->put('/profile', $updateData);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_destroy_account_with_valid_password()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        $response = $this->delete('/profile', [
            'password' => 'password123'
        ]);

        $response->assertRedirect('/');
        
        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
    }

    public function test_destroy_account_with_invalid_password()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        $response = $this->delete('/profile', [
            'password' => 'wrongpassword'
        ]);

        $response->assertSessionHasErrors(['password']);
        
        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    public function test_destroy_account_without_password()
    {
        $response = $this->delete('/profile', []);

        $response->assertSessionHasErrors(['password']);
        
        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
    }

    public function test_destroy_account_deletes_related_data()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        // 创建相关的数据（这里需要根据实际的模型关系调整）
        // 例如：创建用户的物品、图片等

        $response = $this->delete('/profile', [
            'password' => 'password123'
        ]);

        $response->assertRedirect('/');
        
        // 验证用户被删除
        $this->assertDatabaseMissing('users', ['id' => $this->user->id]);
        
        // 验证相关数据也被删除（如果有的话）
        // $this->assertDatabaseMissing('thing_items', ['user_id' => $this->user->id]);
    }

    public function test_destroy_account_logs_out_user()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        $this->assertTrue(auth()->check());

        $response = $this->delete('/profile', [
            'password' => 'password123'
        ]);

        $response->assertRedirect('/');
        
        // 用户应该被登出
        $this->assertFalse(auth()->check());
    }

    public function test_destroy_account_invalidates_session()
    {
        $this->user->update(['password' => Hash::make('password123')]);

        $response = $this->delete('/profile', [
            'password' => 'password123'
        ]);

        $response->assertRedirect('/');
        
        // 会话应该被清除
        $this->assertFalse(auth()->check());
    }

    public function test_edit_requires_authentication()
    {
        auth()->logout();

        $response = $this->get('/profile');

        $response->assertRedirect('/login');
    }

    public function test_update_requires_authentication()
    {
        auth()->logout();

        $response = $this->put('/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_destroy_requires_authentication()
    {
        auth()->logout();

        $response = $this->delete('/profile', [
            'password' => 'password123'
        ]);

        $response->assertRedirect('/login');
    }
} 