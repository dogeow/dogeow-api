<?php

namespace Tests\Feature\Controllers\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MusicControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试音乐目录
        $musicDir = public_path('musics');
        if (! File::exists($musicDir)) {
            File::makeDirectory($musicDir, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // 清理测试文件
        $musicDir = public_path('musics');
        if (File::exists($musicDir)) {
            File::deleteDirectory($musicDir);
        }

        parent::tearDown();
    }

    public function test_index_returns_music_list()
    {
        // 创建测试音乐文件
        $musicDir = public_path('musics');
        $this->createTestMusicFile($musicDir, 'test1.mp3', 1024);
        $this->createTestMusicFile($musicDir, 'test2.ogg', 2048);

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('path', $data[0]);
        $this->assertArrayHasKey('size', $data[0]);
        $this->assertArrayHasKey('extension', $data[0]);
    }

    public function test_index_returns_empty_list_when_no_music_files()
    {
        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json());
    }

    public function test_index_returns_404_when_music_directory_not_exists()
    {
        // 删除音乐目录
        $musicDir = public_path('musics');
        if (File::exists($musicDir)) {
            File::deleteDirectory($musicDir);
        }

        $response = $this->getJson('/api/musics');

        $response->assertStatus(404);
        $response->assertJson(['error' => '音乐目录不存在']);
    }

    public function test_index_filters_only_audio_files()
    {
        $musicDir = public_path('musics');

        // 创建音频文件
        $this->createTestMusicFile($musicDir, 'audio1.mp3', 1024);
        $this->createTestMusicFile($musicDir, 'audio2.ogg', 2048);
        $this->createTestMusicFile($musicDir, 'audio3.wav', 3072);

        // 创建非音频文件
        $this->createTestMusicFile($musicDir, 'document.txt', 100);
        $this->createTestMusicFile($musicDir, 'image.jpg', 200);

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(3, $data);

        $extensions = collect($data)->pluck('extension')->toArray();
        $this->assertContains('mp3', $extensions);
        $this->assertContains('ogg', $extensions);
        $this->assertContains('wav', $extensions);
        $this->assertNotContains('txt', $extensions);
        $this->assertNotContains('jpg', $extensions);
    }

    public function test_index_handles_different_audio_formats()
    {
        $musicDir = public_path('musics');

        $this->createTestMusicFile($musicDir, 'test1.mp3', 1024);
        $this->createTestMusicFile($musicDir, 'test2.ogg', 2048);
        $this->createTestMusicFile($musicDir, 'test3.wav', 3072);
        $this->createTestMusicFile($musicDir, 'test4.flac', 4096);

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(4, $data);

        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('test1', $names);
        $this->assertContains('test2', $names);
        $this->assertContains('test3', $names);
        $this->assertContains('test4', $names);
    }

    public function test_index_returns_correct_file_paths()
    {
        $musicDir = public_path('musics');
        $this->createTestMusicFile($musicDir, 'test.mp3', 1024);

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data);
        $this->assertEquals('/musics/test.mp3', $data[0]['path']);
    }

    public function test_index_returns_correct_file_sizes()
    {
        $musicDir = public_path('musics');
        $this->createTestMusicFile($musicDir, 'test.mp3', 1024);

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data);
        $this->assertEquals(1024, $data[0]['size']);
    }

    public function test_index_returns_correct_extensions()
    {
        $musicDir = public_path('musics');
        $this->createTestMusicFile($musicDir, 'test.mp3', 1024);

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data);
        $this->assertEquals('mp3', $data[0]['extension']);
    }

    public function test_index_handles_files_without_extensions()
    {
        $musicDir = public_path('musics');
        $this->createTestMusicFile($musicDir, 'test', 1024);

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        // 没有扩展名的文件应该被忽略
        $this->assertCount(0, $data);
    }

    public function test_index_handles_special_characters_in_filenames()
    {
        $musicDir = public_path('musics');
        $this->createTestMusicFile($musicDir, 'test file with spaces.mp3', 1024);
        $this->createTestMusicFile($musicDir, 'test-file-with-dashes.ogg', 2048);

        $response = $this->getJson('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data);

        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('test file with spaces', $names);
        $this->assertContains('test-file-with-dashes', $names);
    }

    private function createTestMusicFile(string $directory, string $filename, int $size): void
    {
        $filePath = $directory . '/' . $filename;
        $content = str_repeat('0', $size);
        File::put($filePath, $content);
    }
}
