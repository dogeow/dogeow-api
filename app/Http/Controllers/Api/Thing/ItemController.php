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

class ItemController extends Controller
{
    /**
     * 获取物品列表
     */
    public function index(Request $request)
    {
        $query = Item::with(['user', 'images', 'category', 'spot.room.area']);
        
        // 如果用户已登录，显示公开物品和自己的物品
        if (Auth::check()) {
            $query->where(function($q) {
                $q->where('is_public', true)
                  ->orWhere('user_id', Auth::id());
            });
        } else {
            // 未登录用户只能看到公开物品
            $query->where('is_public', true);
        }
        
        // 搜索关键词
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // 分类筛选
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // 购买时间范围筛选
        if ($request->filled('purchase_date_from')) {
            $query->whereDate('purchase_date', '>=', $request->purchase_date_from);
        }
        if ($request->filled('purchase_date_to')) {
            $query->whereDate('purchase_date', '<=', $request->purchase_date_to);
        }

        // 过期时间范围筛选
        if ($request->filled('expiry_date_from')) {
            $query->whereDate('expiry_date', '>=', $request->expiry_date_from);
        }
        if ($request->filled('expiry_date_to')) {
            $query->whereDate('expiry_date', '<=', $request->expiry_date_to);
        }

        // 购买价格范围筛选
        if ($request->filled('price_from')) {
            $query->where('purchase_price', '>=', $request->price_from);
        }
        if ($request->filled('price_to')) {
            $query->where('purchase_price', '<=', $request->price_to);
        }

        // 存放地点筛选
        if ($request->filled('area_id') || $request->filled('room_id') || $request->filled('spot_id')) {
            $query->whereHas('spot.room.area', function($q) use ($request) {
                // 区域筛选
                if ($request->filled('area_id')) {
                    $q->where('areas.id', $request->area_id);
                }
                
                // 房间筛选
                if ($request->filled('room_id')) {
                    $q->whereHas('rooms', function($q) use ($request) {
                        $q->where('rooms.id', $request->room_id);
                    });
                }
                
                // 具体位置筛选
                if ($request->filled('spot_id')) {
                    $q->whereHas('rooms.spots', function($q) use ($request) {
                        $q->where('spots.id', $request->spot_id);
                    });
                }
            });
        }

        $items = $query->latest()->paginate(10);

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
