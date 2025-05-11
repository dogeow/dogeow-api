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
            
            // 处理图片上传
            if ($request->hasFile('images')) {
                $this->processImages($request->file('images'), $item);
            }
            
            DB::commit();
            
            return response()->json([
                'message' => '物品创建成功',
                'item' => $item->load(['images', 'category', 'spot.room.area'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
            
            // 处理图片上传
            if ($request->hasFile('images')) {
                $this->processImages($request->file('images'), $item);
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
            
            DB::commit();
            
            return response()->json([
                'message' => '物品更新成功',
                'item' => $item->load(['images', 'category', 'spot.room.area'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
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
        
        foreach ($images as $image) {
            try {
                // 记录上传信息
                Log::info('开始处理图片上传', [
                    'filename' => $image->getClientOriginalName(),
                    'size' => $image->getSize(),
                    'mime' => $image->getMimeType(),
                    'extension' => $image->getClientOriginalExtension(),
                    'item_id' => $item->id
                ]);
                
                // 确保有有效的MIME类型
                $mimeType = $image->getMimeType() ?: 'application/octet-stream';
                
                // 验证文件是否为有效图片
                if (!str_starts_with($mimeType, 'image/')) {
                    Log::error('上传文件不是有效的图片类型', [
                        'filename' => $image->getClientOriginalName(), 
                        'mime' => $mimeType
                    ]);
                    $errorCount++;
                    continue;
                }
                
                // 存储原始图片
                $sortOrder++;
                
                // 尝试直接保存文件，而不是使用store方法
                try {
                    $extension = $image->getClientOriginalExtension() ?: 'jpg';
                    $filename = uniqid() . '.' . $extension;
                    $path = 'items/' . $item->id . '/' . $filename;
                    
                    // 先确保目录存在
                    $directory = 'public/items/' . $item->id;
                    if (!file_exists(storage_path('app/' . $directory))) {
                        mkdir(storage_path('app/' . $directory), 0755, true);
                    }
                    
                    // 尝试直接保存文件
                    if ($image->move(storage_path('app/public/items/' . $item->id), $filename)) {
                        Log::info('图片直接保存成功', ['path' => $path]);
                    } else {
                        // 如果直接保存失败，尝试使用store方法
                        $path = $image->store('items/' . $item->id, 'public');
                        if (!$path) {
                            throw new \Exception('无法保存图片');
                        }
                        Log::info('图片使用store方法保存成功', ['path' => $path]);
                    }
                } catch (\Exception $storeException) {
                    Log::error('保存图片失败: ' . $storeException->getMessage(), [
                        'filename' => $image->getClientOriginalName()
                    ]);
                    
                    // 最后尝试
                    $path = $image->store('items/' . $item->id, 'public');
                    if (!$path) {
                        Log::error('图片存储失败 - 最终尝试也失败', [
                            'filename' => $image->getClientOriginalName()
                        ]);
                        $errorCount++;
                        continue;
                    }
                }
                
                // 检查文件是否真的存在
                if (!Storage::disk('public')->exists($path)) {
                    Log::error('图片存储成功但文件不存在', [
                        'path' => $path,
                        'filename' => $image->getClientOriginalName()
                    ]);
                    $errorCount++;
                    continue;
                }
                
                // 创建缩略图
                try {
                    $fileContent = Storage::disk('public')->get($path);
                    
                    if (empty($fileContent)) {
                        Log::error('获取图片内容为空', ['path' => $path]);
                        throw new \Exception('图片内容为空');
                    }
                    
                    $thumbnail = $manager->read($fileContent);
                    $thumbnail->cover(200, 200);
                    $thumbnailPath = 'items/' . $item->id . '/thumb_' . basename($path);
                    Storage::disk('public')->put($thumbnailPath, (string) $thumbnail->encode());
                    
                    // 验证缩略图是否已保存
                    if (!Storage::disk('public')->exists($thumbnailPath)) {
                        Log::error('缩略图保存失败', [
                            'path' => $thumbnailPath
                        ]);
                    }
                } catch (\Exception $thumbException) {
                    Log::error('创建缩略图失败: ' . $thumbException->getMessage(), [
                        'filename' => $image->getClientOriginalName(),
                        'path' => $path
                    ]);
                    $thumbnailPath = null;
                }
                
                // 设置第一张图片为主图
                $isPrimary = $sortOrder === 1 && !ItemImage::where('item_id', $item->id)->where('is_primary', true)->exists();
                
                // 创建图片记录
                $itemImage = ItemImage::create([
                    'item_id' => $item->id,
                    'path' => $path,
                    'thumbnail_path' => $thumbnailPath,
                    'is_primary' => $isPrimary,
                    'sort_order' => $sortOrder,
                ]);
                
                if ($itemImage) {
                    $successCount++;
                    Log::info('图片处理成功', [
                        'image_id' => $itemImage->id,
                        'path' => $path,
                        'thumbnail_path' => $thumbnailPath
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('图片处理异常: ' . $e->getMessage(), [
                    'filename' => $image->getClientOriginalName() ?? 'unknown',
                    'item_id' => $item->id,
                    'trace' => $e->getTraceAsString()
                ]);
                $errorCount++;
            }
        }
        
        Log::info('图片处理完成', [
            'item_id' => $item->id,
            'success' => $successCount,
            'errors' => $errorCount
        ]);
    }
}
