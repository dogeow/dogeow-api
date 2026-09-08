<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\BroadcastServiceProvider;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BroadcastChannelAuthorizationTest extends TestCase
{
    public function test_channel_provider_is_registered_in_the_application(): void
    {
        $this->assertTrue($this->app->getLoadedProviders()[BroadcastServiceProvider::class] ?? false);
        $channels = Broadcast::getChannels();
        foreach (['App.Models.User.{id}', 'user.{userId}', 'user.{userId}.notifications', 'user.{userId}.uploads'] as $pattern) {
            $this->assertTrue($channels->has($pattern));
        }
    }

    public function test_private_notifications_require_the_matching_user(): void
    {
        // 权限来自实际启动时注册的规则；切换测试驱动只用于生成本地签名，不连接网络。
        $channels = Broadcast::getChannels();
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret', 'broadcasting.connections.reverb.app_id' => 'test-app']);
        foreach ($channels as $pattern => $callback) {
            Broadcast::channel($pattern, $callback);
        }
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-user.' . $user->id . '.notifications'])
            ->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1.2', 'channel_name' => 'private-user.' . ($user->id + 1) . '.notifications'])
            ->assertForbidden();
    }
}
