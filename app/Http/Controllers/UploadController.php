<?php

namespace App\Http\Controllers;

use App\Services\FileStorageService;
use App\Services\ImageProcessingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    private FileStorageService $fileStorageService;
    private ImageProcessingService $imageProcessingService;

    public function __construct(
        FileStorageService $fileStorageService,
        ImageProcessingService $imageProcessingService
    ) {
        $this->fileStorageService = $fileStorageService;
        $this->imageProcessingService = $imageProcessingService;
    }

    /**
     * 批量上传图片（支持多张图片同时上传）
     */
    public function uploadBatchImages(Request $request)
    {
        $request->validate([
            'images.*' => 'required|image|max:20480', // 每张图片最大20MB
        ]);
        
        try {
            $userId = Auth::id() ?? 0;
            $dirPath = $this->fileStorageService->createUserDirectory($userId);
            
            if (!$request->hasFile('images')) {
                return response()->json([
                    'message' => '没有找到上传的图片文件'
                ], 400);
            }

            $uploadedImages = [];
            $fileCount = 0;
            $errorCount = 0;
            
            foreach ($request->file('images') as $image) {
                try {
                    if (!$image->isValid()) {
                        Log::error('上传的图片无效', [
                            'error' => $image->getError(),
                            'errorMessage' => $this->getUploadErrorMessage($image->getError())
                        ]);
                        $errorCount++;
                        continue;
                    }

                    // 存储文件
                    $fileInfo = $this->fileStorageService->storeFile($image, $dirPath);
                    
                    // 处理图片
                    $processResult = $this->imageProcessingService->processImage(
                        $fileInfo['origin_path'],
                        $fileInfo['compressed_path']
                    );

                    if (!$processResult['success']) {
                        throw new \Exception($processResult['error']);
                    }

                    // 获取公共URL
                    $urls = $this->fileStorageService->getPublicUrls($userId, $fileInfo);
                    
                    // 添加到上传图片列表
                    $uploadedImages[] = [
                        'path' => 'uploads/' . $userId . '/' . $fileInfo['compressed_filename'],
                        'origin_path' => 'uploads/' . $userId . '/' . $fileInfo['origin_filename'],
                        'url' => $urls['compressed_url'],
                        'origin_url' => $urls['origin_url'],
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
    private function getUploadErrorMessage($errorCode): string
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