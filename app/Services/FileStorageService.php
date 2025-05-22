<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileStorageService
{
    public function storeFile(UploadedFile $file, string $directory): array
    {
        $basename = uniqid();
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        
        // 生成三种文件名
        $compressedFilename = $basename . '.' . $ext;
        $thumbnailFilename = $basename . '-thumb.' . $ext;
        $originFilename = $basename . '-origin.' . $ext;

        // 存储原图
        $file->move($directory, $originFilename);

        return [
            'basename' => $basename,
            'extension' => $ext,
            'compressed_filename' => $compressedFilename,
            'thumbnail_filename' => $thumbnailFilename,
            'origin_filename' => $originFilename,
            'compressed_path' => $directory . '/' . $compressedFilename,
            'thumbnail_path' => $directory . '/' . $thumbnailFilename,
            'origin_path' => $directory . '/' . $originFilename,
        ];
    }

    public function createUserDirectory(int $userId): string
    {
        $dirPath = storage_path('app/public/uploads/' . $userId);
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }
        return $dirPath;
    }

    public function getPublicUrls(string $userId, array $filenames): array
    {
        $baseUrl = 'uploads/' . $userId . '/';
        return [
            'compressed_url' => url('storage/' . $baseUrl . $filenames['compressed_filename']),
            'thumbnail_url' => url('storage/' . $baseUrl . $filenames['thumbnail_filename']),
            'origin_url' => url('storage/' . $baseUrl . $filenames['origin_filename']),
        ];
    }
} 