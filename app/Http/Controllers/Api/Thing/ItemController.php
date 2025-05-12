<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\ItemRequest;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\ItemImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ItemController extends Controller
{
    /**
     * 获取物品列表
     */
    public function index(Request $request)
    {
        $baseQuery = Item::with(['user', 'images', 'category', 'spot.room.area',]);
        
        // 如果用户已登录，显示公开物品和自己的物品
        if (Auth::check()) {
            $baseQuery->where(function($q) {
                $q->where('is_public', true)
                  ->orWhere('user_id', Auth::id());
            });
        } else {
            // 未登录用户只能看到公开物品
            $baseQuery->where('is_public', true);
        }
        
        // 直接处理category_id参数
        if ($request->has('category_id')) {
            $baseQuery->where('category_id', $request->category_id);
        }
        
        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                // 搜索关键词
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->search($value);
                }),
                // 购买时间范围筛选
                AllowedFilter::callback('purchase_date_from', function ($query, $value) {
                    $query->whereDate('purchase_date', '>=', $value);
                }),
                AllowedFilter::callback('purchase_date_to', function ($query, $value) {
                    $query->whereDate('purchase_date', '<=', $value);
                }),
                // 过期时间范围筛选
                AllowedFilter::callback('expiry_date_from', function ($query, $value) {
                    $query->whereDate('expiry_date', '>=', $value);
                }),
                AllowedFilter::callback('expiry_date_to', function ($query, $value) {
                    $query->whereDate('expiry_date', '<=', $value);
                }),
                // 购买价格范围筛选
                AllowedFilter::callback('price_from', function ($query, $value) {
                    $query->where('purchase_price', '>=', $value);
                }),
                AllowedFilter::callback('price_to', function ($query, $value) {
                    $query->where('purchase_price', '<=', $value);
                }),
                // 存放地点筛选
                AllowedFilter::callback('area_id', function ($query, $value) {
                    $query->whereHas('spot.room.area', function($q) use ($value) {
                        $q->where('areas.id', $value);
                    });
                }),
                AllowedFilter::callback('room_id', function ($query, $value) {
                    $query->whereHas('spot.room.area', function($q) use ($value) {
                        $q->whereHas('rooms', function($q) use ($value) {
                            $q->where('rooms.id', $value);
                        });
                    });
                }),
                AllowedFilter::callback('spot_id', function ($query, $value) {
                    $query->whereHas('spot.room.area', function($q) use ($value) {
                        $q->whereHas('rooms.spots', function($q) use ($value) {
                            $q->where('spots.id', $value);
                        });
                    });
                }),
            ])
            ->defaultSort('-created_at');

        $items = $query->paginate(10);

        return response()->json($items);
    }

    /**
     * 存储新创建的物品
     */
    public function store(ItemRequest $request)
    {
        try {
            DB::beginTransaction();
            
            $item = new Item($request->validated());
            $item->user_id = Auth::id();
            $item->save();
            
            // 处理图片
            if ($request->hasFile('images')) {
                // 如果直接提交了图片文件，仍然支持
                $this->processImages($request->file('images'), $item);
            } elseif ($request->has('image_paths') && is_array($request->image_paths)) {
                // 处理临时图片路径
                $this->processTempImages($request->image_paths, $item);
            }
            
            DB::commit();
            
            return response()->json([
                'message' => '物品创建成功',
                'item' => $item->load(['images', 'category', 'spot.room.area'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('创建物品失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => '物品创建失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 显示指定物品
     */
    public function show(Item $item)
    {
        // 检查权限：只有物品所有者或公开物品可以被查看
        if ($item->user_id !== Auth::id() && !$item->is_public) {
            return response()->json(['message' => '无权查看此物品'], 403);
        }
        
        return response()->json($item->load(['images', 'category', 'spot.room.area']));
    }

    /**
     * 更新指定物品
     */
    public function update(ItemRequest $request, Item $item)
    {
        // 检查权限：只有物品所有者可以更新
        if ($item->user_id !== Auth::id()) {
            return response()->json(['message' => '无权更新此物品'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            $item->update($request->validated());
            
            // 处理图片
            if ($request->hasFile('images')) {
                // 如果直接提交了图片文件，仍然支持
                $this->processImages($request->file('images'), $item);
            } elseif ($request->has('image_paths') && is_array($request->image_paths)) {
                // 处理临时图片路径
                $this->processTempImages($request->image_paths, $item);
            }
            
            // 处理图片排序
            if ($request->has('image_order') && is_array($request->image_order)) {
                foreach ($request->image_order as $index => $imageId) {
                    ItemImage::where('id', $imageId)
                        ->where('item_id', $item->id)
                        ->update(['sort_order' => $index]);
                }
            }
            
            // 处理主图设置
            if ($request->has('primary_image_id')) {
                // 先将所有图片设为非主图
                ItemImage::where('item_id', $item->id)->update(['is_primary' => false]);
                
                // 设置新的主图
                ItemImage::where('id', $request->primary_image_id)
                    ->where('item_id', $item->id)
                    ->update(['is_primary' => true]);
            }
            
            // 处理要删除的图片ID
            if ($request->has('delete_image_ids') && is_array($request->delete_image_ids)) {
                foreach ($request->delete_image_ids as $imageId) {
                    $image = ItemImage::where('id', $imageId)
                        ->where('item_id', $item->id)
                        ->first();
                    
                    if ($image) {
                        // 删除物理文件
                        if (file_exists(storage_path('app/public/' . $image->path))) {
                            @unlink(storage_path('app/public/' . $image->path));
                        }
                        if (Storage::exists($image->thumbnail_path)) {
                            Storage::delete($image->thumbnail_path);
                        }
                        
                        // 删除数据库记录
                        $image->delete();
                    }
                }
            }
            
            DB::commit();
            
            return response()->json([
                'message' => '物品更新成功',
                'item' => $item->load(['images', 'category', 'spot.room.area'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('更新物品失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => '物品更新失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 删除指定物品
     */
    public function destroy(Item $item)
    {
        // 检查权限：只有物品所有者可以删除
        if ($item->user_id !== Auth::id()) {
            return response()->json(['message' => '无权删除此物品'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            // 删除相关图片文件
            foreach ($item->images as $image) {
                if (Storage::exists($image->path)) {
                    Storage::delete($image->path);
                }
                if (Storage::exists($image->thumbnail_path)) {
                    Storage::delete($image->thumbnail_path);
                }
            }
            
            // 删除图片记录和物品
            $item->images()->delete();
            $item->delete();
            
            DB::commit();
            
            return response()->json(['message' => '物品删除成功']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => '物品删除失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 获取用户的物品分类
     */
    public function categories()
    {
        $categories = ItemCategory::where('user_id', Auth::id())->get();
        return response()->json($categories);
    }

    /**
     * 处理图片上传
     */
    private function processImages($images, Item $item)
    {
        $sortOrder = ItemImage::where('item_id', $item->id)->max('sort_order') ?? 0;
        $manager = new ImageManager(new Driver());
        $successCount = 0;
        $errorCount = 0;
        
        // 确保存储目录存在
        $dirPath = storage_path('app/public/items/' . $item->id);
        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }
        
        foreach ($images as $image) {
            try {
                // 记录上传信息
                Log::info('开始处理图片上传', [
                    'filename' => $image->getClientOriginalName(),
                    'size' => $image->getSize(),
                    'mime' => $image->getMimeType() ?: 'unknown',
                    'extension' => $image->getClientOriginalExtension() ?: 'jpg',
                    'item_id' => $item->id
                ]);
                
                // 简化存储逻辑，直接使用move方法
                $sortOrder++;
                $filename = uniqid() . '.' . ($image->getClientOriginalExtension() ?: 'jpg');
                $relativePath = 'items/' . $item->id . '/' . $filename;
                
                // 直接将文件移动到public目录
                if ($image->move($dirPath, $filename)) {
                    Log::info('图片成功保存', ['path' => $relativePath]);
                    
                    // 创建缩略图
                    try {
                        $fullPath = $dirPath . '/' . $filename;
                        $thumbnail = $manager->read(file_get_contents($fullPath));
                        $thumbnail->cover(200, 200);
                        
                        $thumbnailFilename = 'thumb_' . $filename;
                        $thumbnailPath = $dirPath . '/' . $thumbnailFilename;
                        $relativeThumbPath = 'items/' . $item->id . '/' . $thumbnailFilename;
                        
                        // 直接写入文件
                        file_put_contents($thumbnailPath, (string) $thumbnail->encode());
                        
                        // 设置图片记录
                        $isPrimary = $sortOrder === 1 && !ItemImage::where('item_id', $item->id)
                            ->where('is_primary', true)->exists();
                        
                        $itemImage = ItemImage::create([
                            'item_id' => $item->id,
                            'path' => $relativePath,
                            'thumbnail_path' => $relativeThumbPath,
                            'is_primary' => $isPrimary,
                            'sort_order' => $sortOrder,
                        ]);
                        
                        $successCount++;
                        Log::info('图片处理成功', [
                            'image_id' => $itemImage->id,
                            'path' => $relativePath,
                            'thumbnail_path' => $relativeThumbPath
                        ]);
                    } catch (\Exception $thumbException) {
                        Log::error('缩略图创建失败: ' . $thumbException->getMessage(), [
                            'file' => $fullPath ?? 'unknown'
                        ]);
                        
                        // 即使缩略图失败，也保存原图记录
                        $isPrimary = $sortOrder === 1 && !ItemImage::where('item_id', $item->id)
                            ->where('is_primary', true)->exists();
                        
                        $itemImage = ItemImage::create([
                            'item_id' => $item->id,
                            'path' => $relativePath,
                            'thumbnail_path' => null, // 缩略图失败
                            'is_primary' => $isPrimary,
                            'sort_order' => $sortOrder,
                        ]);
                        
                        $successCount++;
                    }
                } else {
                    throw new \Exception('移动图片文件失败');
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error('图片处理错误: ' . $e->getMessage(), [
                    'file' => $image->getClientOriginalName(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        Log::info('图片处理完成', [
            'item_id' => $item->id,
            'success' => $successCount,
            'errors' => $errorCount
        ]);
        
        return $successCount;
    }

    /**
     * 上传临时图片
     */
    public function uploadTempImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB
        ]);
        
        try {
            // 获取用户ID
            $userId = Auth::id() ?? 0;
            
            // 获取客户端信息
            $userAgent = $request->header('User-Agent');
            $isIOS = stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false;
            
            // 创建临时目录
            $dirPath = storage_path('app/public/temp/' . $userId);
            if (!file_exists($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            
            $image = $request->file('image');
            
            // 记录上传信息
            Log::info('开始处理临时图片上传', [
                'filename' => $image->getClientOriginalName(),
                'size' => $image->getSize(),
                'mime' => $image->getMimeType() ?: 'unknown',
                'extension' => $image->getClientOriginalExtension() ?: 'jpg',
                'user_id' => $userId,
                'is_valid' => $image->isValid(),
                'error' => $image->getError(),
                'is_ios' => $isIOS,
                'user_agent' => $userAgent
            ]);
            
            // 检查文件有效性
            if (!$image->isValid()) {
                Log::error('上传的图片无效', [
                    'error' => $image->getError(),
                    'errorMessage' => $this->getUploadErrorMessage($image->getError())
                ]);
                
                return response()->json([
                    'message' => '图片上传失败: ' . $this->getUploadErrorMessage($image->getError())
                ], 422);
            }
            
            // 检查iOS设备和大文件的情况
            if ($isIOS && $image->getSize() > 3 * 1024 * 1024) { // 3MB
                Log::warning('iOS设备上传大文件', [
                    'size' => $image->getSize(),
                    'filename' => $image->getClientOriginalName()
                ]);
            }
            
            // 检查文件MIME类型
            $mime = $image->getMimeType();
            if (!$mime || !str_starts_with($mime, 'image/')) {
                $detectedMime = mime_content_type($image->getRealPath());
                Log::warning('图片MIME类型可疑', [
                    'reported_mime' => $mime,
                    'detected_mime' => $detectedMime
                ]);
                
                if ($detectedMime && str_starts_with($detectedMime, 'image/')) {
                    // 使用检测到的MIME类型
                    $mime = $detectedMime;
                } else {
                    // 仍然无法确认为图片，使用默认JPEG类型
                    $mime = 'image/jpeg';
                    Log::warning('无法确认MIME类型，使用默认image/jpeg');
                }
            }
            
            // 生成文件名，确保有扩展名
            $extension = $image->getClientOriginalExtension();
            if (empty($extension)) {
                // 根据MIME类型决定扩展名
                $extension = match($mime) {
                    'image/jpeg', 'image/jpg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/webp' => 'webp',
                    'image/bmp' => 'bmp',
                    'image/heic' => 'heic',
                    default => 'jpg' // 默认使用jpg
                };
            }
            
            // 为iOS设备生成更简单的文件名（避免特殊字符和长文件名）
            if ($isIOS) {
                $filename = 'ios_' . uniqid() . '.' . $extension;
            } else {
                $filename = uniqid() . '.' . $extension;
            }
            
            $relativePath = 'temp/' . $userId . '/' . $filename;
            $fullPath = $dirPath . '/' . $filename;
            
            // 移动文件
            try {
                // 尝试直接移动文件
                if ($image->move($dirPath, $filename)) {
                    Log::info('图片文件成功保存', ['path' => $fullPath]);
                } else {
                    throw new \Exception('无法移动上传文件');
                }
            } catch (\Exception $e) {
                Log::error('移动图片文件失败，尝试替代方法', [
                    'error' => $e->getMessage(),
                    'is_ios' => $isIOS
                ]);
                
                // 尝试用替代方法保存文件
                $content = file_get_contents($image->getRealPath());
                if ($content === false) {
                    throw new \Exception('无法读取上传文件内容');
                }
                
                if (file_put_contents($fullPath, $content) === false) {
                    throw new \Exception('无法写入图片文件');
                }
                
                Log::info('使用替代方法成功保存图片', ['path' => $fullPath]);
            }
            
            // 对于iOS设备和大图片，降低缩略图尺寸以减少内存使用
            $thumbWidth = 200;
            $thumbHeight = 200;
            if ($isIOS && $image->getSize() > 2 * 1024 * 1024) {
                // 对大图使用更小的缩略图尺寸
                $thumbWidth = 150;
                $thumbHeight = 150;
            }
            
            // 创建缩略图
            try {
                $manager = new ImageManager(new Driver());
                
                // 修改读取方式，减少内存使用
                if ($isIOS) {
                    // 对于iOS设备，尝试使用更节省内存的方式
                    $imgResource = @imagecreatefromjpeg($fullPath);
                    if ($imgResource) {
                        // 计算缩放比例
                        $origWidth = imagesx($imgResource);
                        $origHeight = imagesy($imgResource);
                        $ratio = min($thumbWidth / $origWidth, $thumbHeight / $origHeight);
                        $targetWidth = round($origWidth * $ratio);
                        $targetHeight = round($origHeight * $ratio);
                        
                        // 创建缩略图
                        $thumbResource = imagecreatetruecolor($targetWidth, $targetHeight);
                        imagecopyresampled(
                            $thumbResource, $imgResource,
                            0, 0, 0, 0,
                            $targetWidth, $targetHeight, $origWidth, $origHeight
                        );
                        
                        // 保存缩略图
                        $thumbnailFilename = 'thumb_' . $filename;
                        $thumbnailPath = $dirPath . '/' . $thumbnailFilename;
                        $relativeThumbPath = 'temp/' . $userId . '/' . $thumbnailFilename;
                        
                        imagejpeg($thumbResource, $thumbnailPath, 80);
                        imagedestroy($thumbResource);
                        imagedestroy($imgResource);
                        
                        Log::info('使用原生GD库创建缩略图成功', ['path' => $thumbnailPath]);
                    } else {
                        // 如果GD失败，回退到正常方法
                        throw new \Exception('GD库创建缩略图失败，尝试使用Intervention/Image');
                    }
                } else {
                    // 非iOS设备使用标准方法
                    $thumbnail = $manager->read(file_get_contents($fullPath));
                    $thumbnail->cover($thumbWidth, $thumbHeight);
                    
                    $thumbnailFilename = 'thumb_' . $filename;
                    $thumbnailPath = $dirPath . '/' . $thumbnailFilename;
                    $relativeThumbPath = 'temp/' . $userId . '/' . $thumbnailFilename;
                    
                    // 保存缩略图
                    file_put_contents($thumbnailPath, (string) $thumbnail->encode());
                }
                
                Log::info('缩略图创建成功', ['path' => $thumbnailPath]);
            } catch (\Exception $e) {
                Log::error('创建缩略图失败: ' . $e->getMessage(), [
                    'file' => $fullPath,
                    'trace' => $e->getTraceAsString(),
                    'is_ios' => $isIOS
                ]);
                
                // 缩略图创建失败，使用原图路径
                $relativeThumbPath = $relativePath;
            }
            
            // 构建URL - 确保URL不包含特殊字符
            $baseUrl = rtrim(config('app.url'), '/');
            $url = $baseUrl . '/storage/' . $relativePath;
            $thumbnailUrl = $baseUrl . '/storage/' . $relativeThumbPath;
            
            return response()->json([
                'message' => '图片上传成功',
                'path' => $relativePath,
                'thumbnail_path' => $relativeThumbPath,
                'url' => $url,
                'thumbnail_url' => $thumbnailUrl,
                'size' => $image->getSize(),
                'width' => $isIOS ? 'unknown' : null, // 对iOS设备不尝试获取尺寸以节省内存
                'height' => $isIOS ? 'unknown' : null,
            ]);
        } catch (\Exception $e) {
            Log::error('临时图片上传失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_agent' => $request->header('User-Agent')
            ]);
            
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

    /**
     * 处理临时图片，将其移动到正式目录
     */
    private function processTempImages(array $imagePaths, Item $item)
    {
        $sortOrder = ItemImage::where('item_id', $item->id)->max('sort_order') ?? 0;
        $successCount = 0;
        
        // 确保目标目录存在
        $targetDir = storage_path('app/public/items/' . $item->id);
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        foreach ($imagePaths as $index => $path) {
            try {
                $sortOrder++;
                $filename = basename($path);
                $thumbnailPath = dirname($path) . '/thumb_' . $filename;
                
                // 检查源文件是否存在
                $sourcePath = storage_path('app/public/' . $path);
                $sourceThumbPath = storage_path('app/public/' . $thumbnailPath);
                
                if (!file_exists($sourcePath)) {
                    Log::error('临时图片文件不存在', ['path' => $path]);
                    continue;
                }
                
                // 移动文件到最终目录
                $newRelativePath = 'items/' . $item->id . '/' . $filename;
                $newPath = $targetDir . '/' . $filename;
                
                if (copy($sourcePath, $newPath)) {
                    // 移动缩略图
                    $newThumbRelativePath = 'items/' . $item->id . '/thumb_' . $filename;
                    $newThumbPath = $targetDir . '/thumb_' . $filename;
                    
                    if (file_exists($sourceThumbPath)) {
                        copy($sourceThumbPath, $newThumbPath);
                    }
                    
                    // 设置图片记录
                    $isPrimary = $sortOrder === 1 && !ItemImage::where('item_id', $item->id)
                        ->where('is_primary', true)->exists();
                    
                    $itemImage = ItemImage::create([
                        'item_id' => $item->id,
                        'path' => $newRelativePath,
                        'thumbnail_path' => $newThumbRelativePath,
                        'is_primary' => $isPrimary,
                        'sort_order' => $sortOrder,
                    ]);
                    
                    $successCount++;
                    
                    // 删除临时文件
                    @unlink($sourcePath);
                    @unlink($sourceThumbPath);
                } else {
                    Log::error('移动临时图片失败', [
                        'from' => $path,
                        'to' => $newRelativePath
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('处理临时图片出错: ' . $e->getMessage(), [
                    'path' => $path,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        return $successCount;
    }
}
