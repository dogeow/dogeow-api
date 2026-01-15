<?php

namespace App\Console\Commands;

use App\Models\Thing\ItemImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FixItemImagePaths extends Command
{
    protected $signature = 'items:fix-image-paths';
    protected $description = '修复数据库中的图片路径，使其与文件系统一致';

    public function handle()
    {
        $this->info('开始修复图片路径...');
        
        $totalImages = ItemImage::count();
        $processedCount = 0;
        $errorCount = 0;
        
        $this->output->progressStart($totalImages);
        
        ItemImage::chunk(100, function ($images) use (&$processedCount, &$errorCount) {
            foreach ($images as $image) {
                try {
                    // 获取原始路径
                    $originalPath = $image->path;
                    if (empty($originalPath)) {
                        $this->error("图片路径为空: ID {$image->id}");
                        $errorCount++;
                        continue;
                    }
                    
                    // 获取文件名和扩展名
                    $pathInfo = pathinfo($originalPath);
                    $filename = $pathInfo['filename'];
                    $extension = $pathInfo['extension'] ?? 'jpg';
                    
                    // 构建新的路径
                    $itemId = $image->item_id;
                    $newPath = "items/{$itemId}/{$filename}.{$extension}";
                    $newOriginPath = "items/{$itemId}/origin-{$filename}.{$extension}";
                    
                    // 如果路径已经是新格式，跳过
                    if ($originalPath === $newPath) {
                        $processedCount++;
                        $this->output->progressAdvance();
                        continue;
                    }
                    
                    // 检查文件是否存在
                    if (!Storage::disk('public')->exists($newPath)) {
                        $this->error("文件不存在: {$newPath}");
                        $errorCount++;
                        continue;
                    }
                    
                    // 更新数据库记录
                    $image->update([
                        'path' => $newPath,
                        'origin_path' => $newOriginPath,
                    ]);
                    
                    $processedCount++;
                    $this->output->progressAdvance();
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('修复图片路径失败: ' . $e->getMessage(), [
                        'image_id' => $image->id,
                        'path' => $image->path,
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->error("修复图片路径失败: {$image->path} - {$e->getMessage()}");
                }
            }
        });
        
        $this->output->progressFinish();
        
        $this->info("\n修复完成！");
        $this->info("成功修复: {$processedCount} 条记录");
        $this->info("修复失败: {$errorCount} 条记录");
        
        return Command::SUCCESS;
    }
} 