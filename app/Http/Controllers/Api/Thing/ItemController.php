<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\ItemRequest;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\ItemImage; // Keep if $item->images() or similar is used elsewhere
use App\Services\ImageUploadService; // Added
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// Storage might be used by other methods, keep for now or check thoroughly
// Intervention\Image\ImageManager and Intervention\Image\Drivers\Gd\Driver are removed as they are now in the service
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ItemController extends Controller
{
    protected ImageUploadService $imageUploadService;

    public function __construct(ImageUploadService $imageUploadService)
    {
        $this->imageUploadService = $imageUploadService;
    }

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
            $categoryId = $request->category_id;
            
            // 查找该分类
            $category = ItemCategory::find($categoryId);
            
            if ($category) {
                // 如果是父分类，包括该分类及其所有子分类的物品
                if ($category->isParent()) {
                    $childCategoryIds = $category->children()->pluck('id')->toArray();
                    $allCategoryIds = array_merge([$categoryId], $childCategoryIds);
                    $query->whereIn('category_id', $allCategoryIds);
                } else {
                    // 如果是子分类，只查询该子分类的物品
                    $query->where('category_id', $categoryId);
                }
            } else {
                // 如果分类不存在，返回空结果
                $query->where('category_id', $categoryId);
            }
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
                $query->where(function($q) use ($value) {
                    $q->where('area_id', $value)
                      ->orWhereHas('spot.room.area', fn($subQ) => 
                          $subQ->where('thing_areas.id', $value));
                })),
            
            AllowedFilter::callback('room_id', fn($query, $value) => 
                $query->where(function($q) use ($value) {
                    $q->where('room_id', $value)
                      ->orWhereHas('spot.room', fn($subQ) => 
                          $subQ->where('thing_rooms.id', $value));
                })),
            
            AllowedFilter::callback('spot_id', fn($query, $value) => 
                $query->where('spot_id', $value)),
            
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

            if ($request->hasFile('images')) {
                $this->imageUploadService->processUploadedImages($request->file('images'), $item);
            }
            if ($request->has('image_paths')) {
                $this->imageUploadService->processImagePaths($request->image_paths, $item);
            }
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
            
            // 图片同步：只保留 image_ids 中的图片，其余全部删除
            if ($request->has('image_ids')) {
                $keepIds = $request->input('image_ids', []);
                $allIds = $item->images()->pluck('id')->toArray();
                $deleteIds = array_diff($allIds, $keepIds);
                if (!empty($deleteIds)) {
                    $this->imageUploadService->deleteImagesByIds($deleteIds, $item);
                }
            }
            
            if ($request->has('image_paths')) {
                $this->imageUploadService->processImagePaths($request->image_paths, $item);
            }
            if ($request->has('image_order')) {
                $this->imageUploadService->updateImageOrder($request->image_order, $item);
            }
            if ($request->has('primary_image_id')) {
                $this->imageUploadService->setPrimaryImage($request->primary_image_id, $item);
            }
            if ($request->has('delete_images')) {
                $this->imageUploadService->deleteImagesByIds($request->delete_images, $item);
            }
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
            
            $this->imageUploadService->deleteAllItemImages($item); // This already deletes records
            // $item->images()->delete(); // This line is now redundant if deleteAllItemImages handles record deletion.
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

    // Removed handleImages, processImages, handleImageOrder, handlePrimaryImage, handleDeleteImages, deleteItemImages, handleImagePaths

    /**
     * 检查用户是否有权限查看物品
     */
    private function canViewItem(Item $item): bool
    {
        return $item->is_public || (Auth::check() && $item->user_id === Auth::id());
    }

    /**
     * 检查用户是否有权限修改物品
     */
    private function canModifyItem(Item $item): bool
    {
        return Auth::check() && $item->user_id === Auth::id();
    }

    /**
     * 处理标签
     */
    private function handleTags(Request $request, Item $item)
    {
        if ($request->has('tags')) {
            $this->processTags($request->tags, $item);
        }
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
