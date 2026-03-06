<?php

namespace Tests\Feature\Controllers\Api;

use App\Services\UpyunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MusicControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_index_returns_audio_files_only_from_upyun()
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');
        config()->set('services.upyun.domain', 'https://cdn.example.com');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('listDirectory')->once()->with('/music')->andReturn([
                'success' => true,
                'files' => [
                    ['name' => 'audio1.mp3', 'type' => 'audio/mp3', 'length' => 1024],
                    ['name' => 'audio2.ogg', 'type' => 'audio/ogg', 'length' => 2048],
                    ['name' => 'audio3.flac', 'type' => 'audio/flac', 'length' => 3072],
                    ['name' => 'cover.jpg', 'type' => 'image/jpeg', 'length' => 256],
                    ['name' => 'nested', 'type' => 'folder', 'length' => 0],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->times(3)->andReturnUsing(
                fn (string $path): string => 'https://cdn.example.com' . $path
            );
        });

        $response = $this->getJson('/api/musics');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(3, $data);
        $this->assertSame(['audio1', 'audio2', 'audio3'], array_column($data, 'name'));
        $this->assertSame(['mp3', 'ogg', 'flac'], array_column($data, 'extension'));
    }

    public function test_index_encodes_special_characters_in_upyun_paths()
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');
        config()->set('services.upyun.domain', 'https://cdn.example.com');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('listDirectory')->once()->with('/music')->andReturn([
                'success' => true,
                'files' => [
                    ['name' => 'test file 你好.mp3', 'type' => 'audio/mp3', 'length' => 1234],
                ],
            ]);
            $mock->shouldReceive('buildPublicUrl')->once()->with('/music/test%20file%20%E4%BD%A0%E5%A5%BD.mp3')->andReturn(
                'https://cdn.example.com/music/test%20file%20%E4%BD%A0%E5%A5%BD.mp3'
            );
        });

        $response = $this->getJson('/api/musics');

        $response->assertOk()
            ->assertJsonPath('0.name', 'test file 你好')
            ->assertJsonPath('0.path', 'https://cdn.example.com/music/test%20file%20%E4%BD%A0%E5%A5%BD.mp3');
    }

    public function test_index_returns_server_error_when_upyun_listing_fails()
    {
        config()->set('services.upyun.bucket', 'bucket');
        config()->set('services.upyun.operator', 'operator');
        config()->set('services.upyun.password', 'password');

        $this->mock(UpyunService::class, function ($mock): void {
            $mock->shouldReceive('isConfigured')->once()->andReturn(true);
            $mock->shouldReceive('listDirectory')->once()->with('/music')->andReturn([
                'success' => false,
                'message' => '又拍云目录读取失败',
            ]);
        });

        $response = $this->getJson('/api/musics');

        $response->assertStatus(500);
    }
}
