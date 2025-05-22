<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    /**
     * 批量上传图片（支持多张图片同时上传）
     */
    public function uploadBatchImages(Request $request)
    {
        $request->validate([
            'images.*' => 'required|image|max:20480', // 每张图片最大20MB
        ]);
        
        try {
            // 获取用户ID
            $userId = Auth::id() ?? 0;
            
            // 创建用户目录
            $dirPath = storage_path('app/public/uploads/' . $userId);
            if (!file_exists($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            
            $manager = new ImageManager(new Driver());
            $uploadedImages = [];
            $fileCount = 0;
            $errorCount = 0;
            
            // 检查是否有文件上传
            if (!$request->hasFile('images')) {
                return response()->json([
                    'message' => '没有找到上传的图片文件'
                ], 400);
            }
            
            foreach ($request->file('images') as $image) {
                try {
                    // 检查文件有效性
                    if (!$image->isValid()) {
                        Log::error('上传的图片无效', [
                            'error' => $image->getError(),
                            'errorMessage' => $this->getUploadErrorMessage($image->getError())
                        ]);
                        $errorCount++;
                        continue;
                    }
                    
                    // 生成文件名和路径
                    $filename = uniqid() . '.' . ($image->getClientOriginalExtension() ?: 'jpg');
                    $fullPath = $dirPath . '/' . $filename;
                    $relativePath = 'uploads/' . $userId . '/' . $filename;
                    
                    // 保存原图
                    $originFilename = 'origin-' . $filename;
                    $originPath = $dirPath . '/' . $originFilename;
                    $relativeOriginPath = 'uploads/' . $userId . '/' . $originFilename;
                    $image->move($dirPath, $originFilename);
                    
                    // 创建缩略图
                    $thumbnailFilename = $filename . '-thumb';
                    $thumbnailPath = $dirPath . '/' . $thumbnailFilename;
                    $relativeThumbPath = 'uploads/' . $userId . '/' . $thumbnailFilename;
                    
                    try {
                        // 读取原图
                        $img = $manager->read($image->getRealPath());
                        
                        // 创建缩略图
                        $thumbnail = $manager->read($image->getRealPath());
                        $thumbnail->cover(200, 200);
                        $thumbnail->save($thumbnailPath);
                        
                        // 创建压缩图（最大800px）
                        $compressed = $manager->read($image->getRealPath());
                        $compressed->resize(800, 800, function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        });
                        $compressed->save($fullPath);
                        
                    } catch (\Exception $thumbException) {
                        Log::error('处理图片失败: ' . $thumbException->getMessage(), [
                            'file' => $fullPath,
                            'trace' => $thumbException->getTraceAsString()
                        ]);
                        $relativeThumbPath = $relativePath;
                    }
                    
                    // 获取图片的公共URL
                    $url = url('storage/' . $relativePath);
                    $thumbnailUrl = url('storage/' . $relativeThumbPath);
                    $originUrl = url('storage/' . $relativeOriginPath);
                    
                    // 添加到上传图片列表
                    $uploadedImages[] = [
                        'path' => $relativePath,
                        'thumbnail_path' => $relativeThumbPath,
                        'origin_path' => $relativeOriginPath,
                        'url' => $url,
                        'thumbnail_url' => $thumbnailUrl,
                        'origin_url' => $originUrl,
                    ];
                    
                    $fileCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error('处理单张图片失败: ' . $e->getMessage(), [
                        'file' => $image->getClientOriginalName(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            if ($fileCount == 0 && $errorCount > 0) {
                return response()->json([
                    'message' => '所有图片上传失败'
                ], 500);
            }
            
            return response()->json($uploadedImages);
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => '图片上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取上传错误信息
     */
    private function getUploadErrorMessage($errorCode)
    {
        return match($errorCode) {
            UPLOAD_ERR_INI_SIZE => '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值',
            UPLOAD_ERR_FORM_SIZE => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传因扩展程序而停止',
            default => '未知上传错误'
        };
    }
}