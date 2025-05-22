<?php

namespace App\Console\Commands;

use App\Models\Thing\ItemImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class ProcessItemImages extends Command
{
    protected $signature = 'items:process-images';
    protected $description = '处理物品图片，为 origin- 开头的图片创建压缩版本';

    public function handle()
    {
        $this->info('开始处理物品图片...');
        
        $manager = new ImageManager(new Driver());
        $processedCount = 0;
        $errorCount = 0;
        
        // 获取所有物品目录
        $itemsPath = storage_path('app/public/items');
        if (!File::exists($itemsPath)) {
            $this->error('物品目录不存在');
            return Command::FAILURE;
        }
        
        $itemDirs = File::directories($itemsPath);
        $totalDirs = count($itemDirs);
        
        $this->output->progressStart($totalDirs);
        
        foreach ($itemDirs as $itemDir) {
            try {
                $itemId = basename($itemDir);
                $files = File::files($itemDir);
                
                foreach ($files as $file) {
                    $filename = $file->getFilename();
                    
                    // 只处理 origin- 开头的文件
                    if (!str_starts_with($filename, 'origin-')) {
                        continue;
                    }
                    
                    try {
                        // 生成压缩版本的文件名（去掉 origin- 前缀）
                        $compressedFilename = substr($filename, 7); // 去掉 'origin-' 前缀
                        $compressedPath = $itemDir . '/' . $compressedFilename;
                        
                        // 创建800宽度的版本
                        $compressed = $manager->read($file->getPathname());
                        $compressed->resize(800, 800, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                        $compressed->save($compressedPath);
                        
                        $processedCount++;
                        
                    } catch (\Exception $e) {
                        $errorCount++;
                        Log::error('处理图片失败: ' . $e->getMessage(), [
                            'file' => $filename,
                            'trace' => $e->getTraceAsString()
                        ]);
                        $this->error("处理图片失败: {$filename} - {$e->getMessage()}");
                    }
                }
                
                $this->output->progressAdvance();
                
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('处理目录失败: ' . $e->getMessage(), [
                    'dir' => $itemDir,
                    'trace' => $e->getTraceAsString()
                ]);
                $this->error("处理目录失败: {$itemDir} - {$e->getMessage()}");
            }
        }
        
        $this->output->progressFinish();
        
        $this->info("\n处理完成！");
        $this->info("成功处理: {$processedCount} 张图片");
        $this->info("处理失败: {$errorCount} 张图片");
        
        return Command::SUCCESS;
    }
}