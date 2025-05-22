<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Log;

class ImageProcessingService
{
    private ImageManager $manager;
    private const THUMBNAIL_MIN_SIZE = 200;
    private const COMPRESSED_MAX_SIZE = 800;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function processImage(string $originPath, string $compressedPath, string $thumbnailPath): array
    {
        try {
            $img = $this->manager->read($originPath);
            
            // 处理缩略图
            $this->createThumbnail($originPath, $thumbnailPath);
            
            // 处理压缩图
            $this->createCompressedImage($originPath, $compressedPath);
            
            return [
                'success' => true,
                'width' => $img->width(),
                'height' => $img->height()
            ];
        } catch (\Exception $e) {
            Log::error('图片处理失败: ' . $e->getMessage(), [
                'file' => $originPath,
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function createThumbnail(string $originPath, string $thumbnailPath): void
    {
        $thumbnail = $this->manager->read($originPath);
        $thumbWidth = $thumbnail->width();
        $thumbHeight = $thumbnail->height();

        if ($thumbWidth < self::THUMBNAIL_MIN_SIZE && $thumbHeight < self::THUMBNAIL_MIN_SIZE) {
            // 原图宽高都小于200，不缩放
        } elseif ($thumbWidth <= $thumbHeight) {
            // 高图或正方形，宽缩放到200
            $thumbnail->scale(width: self::THUMBNAIL_MIN_SIZE);
        } else {
            // 宽图，高缩放到200
            $thumbnail->scale(height: self::THUMBNAIL_MIN_SIZE);
        }
        
        $thumbnail->save($thumbnailPath);
    }

    private function createCompressedImage(string $originPath, string $compressedPath): void
    {
        $compressed = $this->manager->read($originPath);
        $width = $compressed->width();
        $height = $compressed->height();

        if ($width > self::COMPRESSED_MAX_SIZE || $height > self::COMPRESSED_MAX_SIZE) {
            if ($width >= $height) {
                $compressed->scale(width: self::COMPRESSED_MAX_SIZE);
            } else {
                $compressed->scale(height: self::COMPRESSED_MAX_SIZE);
            }
        }
        
        $compressed->save($compressedPath);
    }
} 