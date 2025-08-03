<?php

namespace Tests\Feature\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MusicControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_get_music_list()
    {
        // 创建测试音乐目录和文件
        $musicDir = public_path('musics');
        if (!File::exists($musicDir)) {
            File::makeDirectory($musicDir, 0755, true);
        }

        // 创建测试音乐文件
        $testFiles = [
            'test1.mp3' => 'test content 1',
            'test2.ogg' => 'test content 2',
            'test3.wav' => 'test content 3',
            'ignore.txt' => 'should be ignored',
        ];

        foreach ($testFiles as $filename => $content) {
            File::put($musicDir . '/' . $filename, $content);
        }

        $response = $this->get('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(3, $data); // 只包含音频文件
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('path', $data[0]);
        $this->assertArrayHasKey('size', $data[0]);
        $this->assertArrayHasKey('extension', $data[0]);

        // 验证只包含音频文件
        $extensions = array_column($data, 'extension');
        $this->assertContains('mp3', $extensions);
        $this->assertContains('ogg', $extensions);
        $this->assertContains('wav', $extensions);
        $this->assertNotContains('txt', $extensions);

        // 清理测试文件
        foreach (array_keys($testFiles) as $filename) {
            File::delete($musicDir . '/' . $filename);
        }
    }

    /** @test */
    public function it_returns_error_when_music_directory_not_exists()
    {
        // 确保音乐目录不存在
        $musicDir = public_path('musics');
        if (File::exists($musicDir)) {
            File::deleteDirectory($musicDir);
        }

        $response = $this->get('/api/musics');

        $response->assertStatus(404);
        $response->assertJson([
            'error' => '音乐目录不存在'
        ]);
    }

    /** @test */
    public function it_handles_empty_music_directory()
    {
        // 创建空的音乐目录
        $musicDir = public_path('musics');
        if (!File::exists($musicDir)) {
            File::makeDirectory($musicDir, 0755, true);
        }

        // 确保目录为空
        $files = File::files($musicDir);
        foreach ($files as $file) {
            File::delete($file->getPathname());
        }

        $response = $this->get('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    /** @test */
    public function it_filters_only_audio_files()
    {
        $musicDir = public_path('musics');
        if (!File::exists($musicDir)) {
            File::makeDirectory($musicDir, 0755, true);
        }

        // 创建混合文件类型
        $testFiles = [
            'audio1.mp3' => 'audio content',
            'audio2.ogg' => 'audio content',
            'document.pdf' => 'document content',
            'image.jpg' => 'image content',
            'script.js' => 'script content',
            'audio3.flac' => 'audio content',
        ];

        foreach ($testFiles as $filename => $content) {
            File::put($musicDir . '/' . $filename, $content);
        }

        $response = $this->get('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        // 只应该包含音频文件
        $this->assertCount(3, $data);
        
        $filenames = array_column($data, 'name');
        $this->assertContains('audio1', $filenames);
        $this->assertContains('audio2', $filenames);
        $this->assertContains('audio3', $filenames);
        $this->assertNotContains('document', $filenames);
        $this->assertNotContains('image', $filenames);
        $this->assertNotContains('script', $filenames);

        // 清理测试文件
        foreach (array_keys($testFiles) as $filename) {
            File::delete($musicDir . '/' . $filename);
        }
    }

    /** @test */
    public function it_returns_correct_file_information()
    {
        $musicDir = public_path('musics');
        if (!File::exists($musicDir)) {
            File::makeDirectory($musicDir, 0755, true);
        }

        // 创建测试文件
        $filename = 'test-song.mp3';
        $content = 'test audio content';
        File::put($musicDir . '/' . $filename, $content);

        $response = $this->get('/api/musics');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data);
        $music = $data[0];

        $this->assertEquals('test-song', $music['name']);
        $this->assertEquals('/musics/test-song.mp3', $music['path']);
        $this->assertEquals('mp3', $music['extension']);
        $this->assertEquals(strlen($content), $music['size']);

        // 清理测试文件
        File::delete($musicDir . '/' . $filename);
    }
} 