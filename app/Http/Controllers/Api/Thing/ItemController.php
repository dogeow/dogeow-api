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
        
        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters([
                AllowedFilter::callback('category_id', function ($query, $value) {
                    $query->where('category_id', $value);
                }),
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
        
        foreach ($images as $image) {
            $sortOrder++;
            $path = $image->store('items/' . $item->id, 'public');
            
            // 创建缩略图
            $thumbnail = $manager->read(Storage::disk('public')->get($path));
            $thumbnail->cover(200, 200);
            $thumbnailPath = 'items/' . $item->id . '/thumb_' . basename($path);
            Storage::disk('public')->put($thumbnailPath, (string) $thumbnail->encode());
            
            // 设置第一张图片为主图
            $isPrimary = $sortOrder === 1 && !ItemImage::where('item_id', $item->id)->where('is_primary', true)->exists();
            
            ItemImage::create([
                'item_id' => $item->id,
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'is_primary' => $isPrimary,
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
