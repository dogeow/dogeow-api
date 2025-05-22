<?php

namespace App\Console\Commands;

use App\Models\Thing\ItemImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Storage;

class ProcessItemImages extends Command
{
    protected $signature = 'items:process-images';
    protected $description = '处理物品图片，添加800宽度的版本并重命名';

    public function handle()
    {
        $this->info('开始处理物品图片...');
        
        $manager = new ImageManager(new Driver());
        $totalImages = ItemImage::count();
        $processedCount = 0;
        $errorCount = 0;
        
        $this->output->progressStart($totalImages);
        
        ItemImage::chunk(100, function ($images) use ($manager, &$processedCount, &$errorCount) {
            foreach ($images as $image) {
                try {
                    $itemId = $image->item_id;
                    $dirPath = storage_path('app/public/items/' . $itemId);
                    
                    // 获取原图路径
                    $originalPath = storage_path('app/public/' . $image->path);
                    if (!file_exists($originalPath)) {
                        $this->error("原图不存在: {$image->path}");
                        $errorCount++;
                        continue;
                    }
                    
                    // 获取原文件名
                    $originalFilename = basename($originalPath);
                    $extension = pathinfo($originalPath, PATHINFO_EXTENSION) ?: 'jpg';
                    
                    // 先保存原图
                    $originFilename = 'origin-' . $originalFilename;
                    $originPath = 'items/' . $itemId . '/' . $originFilename;
                    $fullOriginPath = storage_path('app/public/' . $originPath);
                    copy($originalPath, $fullOriginPath);
                    
                    // 创建800宽度的版本
                    $compressed = $manager->read($originalPath);
                    $compressed->resize(800, 800, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                    
                    // 创建缩略图
                    $thumbnailFilename = $originalFilename . '-thumb';
                    $thumbnailPath = 'items/' . $itemId . '/' . $thumbnailFilename;
                    $fullThumbPath = storage_path('app/public/' . $thumbnailPath);
                    
                    $thumbnail = $manager->read($originalPath);
                    $thumbnail->cover(200, 200);
                    
                    // 更新数据库记录
                    $image->update([
                        'thumbnail_path' => $thumbnailPath,
                        'origin_path' => $originPath,
                    ]);
                    
                    $processedCount++;
                    $this->output->progressAdvance();
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('处理图片失败: ' . $e->getMessage(), [
                        'image_id' => $image->id,
                        'path' => $image->path,
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->error("处理图片失败: {$image->path} - {$e->getMessage()}");
                }
            }
        });
        
        $this->output->progressFinish();
        
        $this->info("\n处理完成！");
        $this->info("成功处理: {$processedCount} 张图片");
        $this->info("处理失败: {$errorCount} 张图片");
        
        return Command::SUCCESS;
    }
}