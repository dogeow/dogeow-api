<?php

namespace Tests\Unit\Services;

use App\Services\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileStorageServiceTest extends TestCase
{

    protected FileStorageService $fileStorageService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fileStorageService = new FileStorageService();
        Storage::fake('public');
    }

    public function test_store_file_successfully()
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $directory = storage_path('app/public/uploads/1');

        $result = $this->fileStorageService->storeFile($file, $directory);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('basename', $result);
        $this->assertArrayHasKey('extension', $result);
        $this->assertArrayHasKey('compressed_filename', $result);
        $this->assertArrayHasKey('thumbnail_filename', $result);
        $this->assertArrayHasKey('origin_filename', $result);
        $this->assertArrayHasKey('compressed_path', $result);
        $this->assertArrayHasKey('origin_path', $result);
        
        $this->assertEquals('jpg', $result['extension']);
        $this->assertStringEndsWith('.jpg', $result['compressed_filename']);
        $this->assertStringEndsWith('-thumb.jpg', $result['thumbnail_filename']);
        $this->assertStringEndsWith('-origin.jpg', $result['origin_filename']);
    }

    public function test_store_file_without_extension()
    {
        $file = UploadedFile::fake()->create('test', 100);
        $directory = storage_path('app/public/uploads/1');

        $result = $this->fileStorageService->storeFile($file, $directory);

        $this->assertEquals('jpg', $result['extension']); // Default extension
        $this->assertStringEndsWith('.jpg', $result['compressed_filename']);
    }

    public function test_store_file_with_different_extension()
    {
        $file = UploadedFile::fake()->image('test.png');
        $directory = storage_path('app/public/uploads/1');

        $result = $this->fileStorageService->storeFile($file, $directory);

        $this->assertEquals('png', $result['extension']);
        $this->assertStringEndsWith('.png', $result['compressed_filename']);
        $this->assertStringEndsWith('-thumb.png', $result['thumbnail_filename']);
        $this->assertStringEndsWith('-origin.png', $result['origin_filename']);
    }

    public function test_create_user_directory()
    {
        $userId = 123;
        $expectedPath = storage_path('app/public/uploads/' . $userId);

        $result = $this->fileStorageService->createUserDirectory($userId);

        $this->assertEquals($expectedPath, $result);
        $this->assertDirectoryExists($expectedPath);
    }

    public function test_create_user_directory_when_already_exists()
    {
        $userId = 123;
        $expectedPath = storage_path('app/public/uploads/' . $userId);

        // Create directory first
        if (!file_exists($expectedPath)) {
            mkdir($expectedPath, 0755, true);
        }

        $result = $this->fileStorageService->createUserDirectory($userId);

        $this->assertEquals($expectedPath, $result);
        $this->assertDirectoryExists($expectedPath);
    }

    public function test_get_public_urls()
    {
        $userId = '123';
        $filenames = [
            'compressed_filename' => 'abc123.jpg',
            'thumbnail_filename' => 'abc123-thumb.jpg',
            'origin_filename' => 'abc123-origin.jpg',
        ];

        $result = $this->fileStorageService->getPublicUrls($userId, $filenames);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('compressed_url', $result);
        $this->assertArrayHasKey('thumbnail_url', $result);
        $this->assertArrayHasKey('origin_url', $result);

        $this->assertStringContainsString('storage/uploads/123/abc123.jpg', $result['compressed_url']);
        $this->assertStringContainsString('storage/uploads/123/abc123-thumb.jpg', $result['thumbnail_url']);
        $this->assertStringContainsString('storage/uploads/123/abc123-origin.jpg', $result['origin_url']);
    }

    public function test_get_public_urls_with_different_extensions()
    {
        $userId = '456';
        $filenames = [
            'compressed_filename' => 'def456.png',
            'thumbnail_filename' => 'def456-thumb.png',
            'origin_filename' => 'def456-origin.png',
        ];

        $result = $this->fileStorageService->getPublicUrls($userId, $filenames);

        $this->assertStringContainsString('storage/uploads/456/def456.png', $result['compressed_url']);
        $this->assertStringContainsString('storage/uploads/456/def456-thumb.png', $result['thumbnail_url']);
        $this->assertStringContainsString('storage/uploads/456/def456-origin.png', $result['origin_url']);
    }

    public function test_store_file_generates_unique_basenames()
    {
        $file1 = UploadedFile::fake()->image('test1.jpg');
        $file2 = UploadedFile::fake()->image('test2.jpg');
        $directory = storage_path('app/public/uploads/1');

        $result1 = $this->fileStorageService->storeFile($file1, $directory);
        $result2 = $this->fileStorageService->storeFile($file2, $directory);

        $this->assertNotEquals($result1['basename'], $result2['basename']);
    }
} 