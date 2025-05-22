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
        $baseQuery = Item::with(['user', 'images', 'category', 'spot.room.area', 'tags']);
        
        // 构建查询条件
        $this->buildVisibilityQuery($baseQuery);
        $this->buildOwnershipQuery($baseQuery, $request);
        $this->buildCategoryQuery($baseQuery, $request);
        
        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters($this->getAllowedFilters())
            ->defaultSort('-created_at');

        return response()->json($query->paginate(10));
    }

    /**
     * 构建可见性查询条件
     */
    private function buildVisibilityQuery($query)
    {
        if (Auth::check()) {
            $query->where(function($q) {
                $q->where('is_public', true)
                  ->orWhere('user_id', Auth::id());
            });
        } else {
            $query->where('is_public', true);
        }
    }

    /**
     * 构建所有权查询条件
     */
    private function buildOwnershipQuery($query, Request $request)
    {
        if ($request->has('own') && Auth::check()) {
            $query->where('user_id', Auth::id());
        }
    }

    /**
     * 构建分类查询条件
     */
    private function buildCategoryQuery($query, Request $request)
    {
        if ($request->has('uncategorized') && $request->uncategorized) {
            $query->whereNull('category_id');
        } elseif ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
    }

    /**
     * 获取允许的过滤器
     */
    private function getAllowedFilters()
    {
        return [
            AllowedFilter::callback('name', fn($query, $value) => 
                $query->where('name', 'like', "%{$value}%")),
            
            AllowedFilter::callback('description', fn($query, $value) => 
                $query->where('description', 'like', "%{$value}%")),
            
            AllowedFilter::callback('status', fn($query, $value) => 
                $value !== 'all' ? $query->where('status', $value) : null),
            
            AllowedFilter::callback('tags', fn($query, $value) => 
                $query->whereHas('tags', fn($q) => 
                    $q->whereIn('thing_tags.id', is_array($value) ? $value : explode(',', $value)))),
            
            AllowedFilter::callback('search', fn($query, $value) => 
                $query->search($value)),
            
            AllowedFilter::callback('purchase_date_from', fn($query, $value) => 
                $query->whereDate('purchase_date', '>=', $value)),
            
            AllowedFilter::callback('purchase_date_to', fn($query, $value) => 
                $query->whereDate('purchase_date', '<=', $value)),
            
            AllowedFilter::callback('expiry_date_from', fn($query, $value) => 
                $query->whereDate('expiry_date', '>=', $value)),
            
            AllowedFilter::callback('expiry_date_to', fn($query, $value) => 
                $query->whereDate('expiry_date', '<=', $value)),
            
            AllowedFilter::callback('price_from', fn($query, $value) => 
                $query->where('purchase_price', '>=', $value)),
            
            AllowedFilter::callback('price_to', fn($query, $value) => 
                $query->where('purchase_price', '<=', $value)),
            
            AllowedFilter::callback('area_id', fn($query, $value) => 
                $query->whereHas('spot.room.area', fn($q) => 
                    $q->where('areas.id', $value))),
            
            AllowedFilter::callback('room_id', fn($query, $value) => 
                $query->whereHas('spot.room.area', fn($q) => 
                    $q->whereHas('rooms', fn($q) => 
                        $q->where('rooms.id', $value)))),
            
            AllowedFilter::callback('spot_id', fn($query, $value) => 
                $query->whereHas('spot.room.area', fn($q) => 
                    $q->whereHas('rooms.spots', fn($q) => 
                        $q->where('spots.id', $value)))),
            
            AllowedFilter::callback('category_id', fn($query, $value) => 
                $query->where('category_id', $value)),
        ];
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
            
            $this->handleImages($request, $item);
            $this->handleTags($request, $item);
            
            DB::commit();
            
            return response()->json([
                'message' => '物品创建成功',
                'item' => $item->load(['images', 'category', 'spot.room.area', 'tags'])
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
        if (!$this->canViewItem($item)) {
            return response()->json(['message' => '无权查看此物品'], 403);
        }
        
        return response()->json($item->load(['images', 'category', 'spot.room.area', 'tags']));
    }

    /**
     * 更新指定物品
     */
    public function update(ItemRequest $request, Item $item)
    {
        if (!$this->canModifyItem($item)) {
            return response()->json(['message' => '无权更新此物品'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            $item->update($request->validated());
            
            $this->handleImages($request, $item);
            $this->handleImageOrder($request, $item);
            $this->handlePrimaryImage($request, $item);
            $this->handleDeleteImages($request, $item);
            $this->handleTags($request, $item);
            
            DB::commit();
            
            return response()->json([
                'message' => '物品更新成功',
                'item' => $item->load(['images', 'category', 'spot.room.area', 'tags'])
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
        if (!$this->canModifyItem($item)) {
            return response()->json(['message' => '无权删除此物品'], 403);
        }
        
        try {
            DB::beginTransaction();
            
            $this->deleteItemImages($item);
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
        return response()->json(ItemCategory::where('user_id', Auth::id())->get());
    }

    /**
     * 处理图片相关操作
     */
    private function handleImages(Request $request, Item $item)
    {
        if ($request->hasFile('images')) {
            $this->processImages($request->file('images'), $item);
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
     * 处理标签
     */
    private function processTags(array $tagIds, Item $item)
    {
        // 清除当前的标签关联
        $item->tags()->detach();
        
        // 如果没有标签，直接返回
        if (empty($tagIds)) {
            return;
        }
        
        // 重新关联标签
        $item->tags()->attach($tagIds);
    }
}
