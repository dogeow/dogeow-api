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
                        if ($image->thumbnail_path && file_exists(storage_path('app/public/' . $image->thumbnail_path))) {
                            @unlink(storage_path('app/public/' . $image->thumbnail_path));
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
            $userId = Auth::id();
            
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
                'user_id' => $userId
            ]);
            
            // 生成文件名
            $filename = uniqid() . '.' . ($image->getClientOriginalExtension() ?: 'jpg');
            $relativePath = 'temp/' . $userId . '/' . $filename;
            
            // 移动文件
            if ($image->move($dirPath, $filename)) {
                $fullPath = $dirPath . '/' . $filename;
                
                // 创建缩略图
                $manager = new ImageManager(new Driver());
                $thumbnail = $manager->read(file_get_contents($fullPath));
                $thumbnail->cover(200, 200);
                
                $thumbnailFilename = 'thumb_' . $filename;
                $thumbnailPath = $dirPath . '/' . $thumbnailFilename;
                $relativeThumbPath = 'temp/' . $userId . '/' . $thumbnailFilename;
                
                // 保存缩略图
                file_put_contents($thumbnailPath, (string) $thumbnail->encode());
                
                // 构建URL
                $baseUrl = config('app.url');
                $url = $baseUrl . '/storage/' . $relativePath;
                $thumbnailUrl = $baseUrl . '/storage/' . $relativeThumbPath;
                
                return response()->json([
                    'message' => '图片上传成功',
                    'path' => $relativePath,
                    'thumbnail_path' => $relativeThumbPath,
                    'url' => $url,
                    'thumbnail_url' => $thumbnailUrl,
                ]);
            } else {
                return response()->json(['message' => '图片上传失败'], 500);
            }
        } catch (\Exception $e) {
            Log::error('临时图片上传失败: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => '图片上传失败: ' . $e->getMessage()
            ], 500);
        }
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
