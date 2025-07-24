<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\ItemRequest;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class ItemController extends Controller
{
    private const DEFAULT_PAGE_SIZE = 10;
    private const ITEM_RELATIONS = ['user', 'images', 'category', 'spot.room.area', 'tags'];

    public function __construct(
        private readonly ImageUploadService $imageUploadService
    ) {}

    /**
     * 获取物品列表
     */
    public function index(Request $request)
    {
        $baseQuery = Item::with(self::ITEM_RELATIONS);
        
        $this->buildVisibilityQuery($baseQuery);
        
        $query = QueryBuilder::for($baseQuery)
            ->allowedFilters($this->getAllowedFilters())
            ->defaultSort('-created_at');

        return response()->json($query->paginate(self::DEFAULT_PAGE_SIZE));
    }

    /**
     * 构建可见性查询条件
     */
    private function buildVisibilityQuery($query): void
    {
        if (Auth::check()) {
            $query->where(fn($q) => $q->where('is_public', true)->orWhere('user_id', Auth::id()));
        } else {
            $query->where('is_public', true);
        }
    }


    /**
     * 获取允许的过滤器
     */
    private function getAllowedFilters(): array
    {
        return [
            AllowedFilter::callback('name', fn($query, $value) => 
                $query->where('name', 'like', "%{$value}%")),
            
            AllowedFilter::callback('description', fn($query, $value) => 
                $query->where('description', 'like', "%{$value}%")),
            
            AllowedFilter::callback('status', fn($query, $value) => 
                $value !== 'all' ? $query->where('status', $value) : $query),
            
            AllowedFilter::callback('tags', fn($query, $value) => 
                $query->whereHas('tags', fn($q) => 
                    $q->whereIn('thing_tags.id', is_array($value) ? $value : explode(',', $value)))),
            
            AllowedFilter::callback('search', fn($query, $value) => $query->search($value)),
            
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
                $query->where(fn($q) => $q->where('area_id', $value)
                    ->orWhereHas('spot.room.area', fn($subQ) => 
                        $subQ->where('thing_areas.id', $value)))),
            
            AllowedFilter::callback('room_id', fn($query, $value) => 
                $query->where(fn($q) => $q->where('room_id', $value)
                    ->orWhereHas('spot.room', fn($subQ) => 
                        $subQ->where('thing_rooms.id', $value)))),
            
            AllowedFilter::callback('spot_id', fn($query, $value) => 
                $query->where('spot_id', $value)),
            
            AllowedFilter::callback('category_id', fn($query, $value) => 
                $this->applyCategoryFilter($query, $value)),
            
            AllowedFilter::callback('own', fn($query, $value) => 
                $value && Auth::check() ? $query->where('user_id', Auth::id()) : $query),
        ];
    }

    /**
     * 存储新创建的物品
     */
    public function store(ItemRequest $request)
    {
        return DB::transaction(function () use ($request) {
            $item = Item::create([
                ...$request->validated(),
                'user_id' => Auth::id()
            ]);

            $this->processItemImages($request, $item);
            $this->handleTags($request, $item);
            
            return response()->json([
                'message' => '物品创建成功',
                'item' => $item->load(self::ITEM_RELATIONS)
            ], 201);
        });
    }

    /**
     * 显示指定物品
     */
    public function show(Item $item)
    {
        if (!$this->canViewItem($item)) {
            return response()->json(['message' => '无权查看此物品'], 403);
        }
        
        return response()->json($item->load(self::ITEM_RELATIONS));
    }

    /**
     * 更新指定物品
     */
    public function update(ItemRequest $request, Item $item)
    {
        if (!$this->canModifyItem($item)) {
            return response()->json(['message' => '无权更新此物品'], 403);
        }
        
        return DB::transaction(function () use ($request, $item) {
            $item->update($request->validated());
            
            $this->processItemImageUpdates($request, $item);
            $this->handleTags($request, $item);
            
            return response()->json([
                'message' => '物品更新成功',
                'item' => $item->load(self::ITEM_RELATIONS)
            ]);
        });
    }

    /**
     * 删除指定物品
     */
    public function destroy(Item $item)
    {
        if (!$this->canModifyItem($item)) {
            return response()->json(['message' => '无权删除此物品'], 403);
        }
        
        return DB::transaction(function () use ($item) {
            $this->imageUploadService->deleteAllItemImages($item);
            $item->delete();
            
            return response()->json(['message' => '物品删除成功']);
        });
    }

    /**
     * 搜索物品
     */
    public function search(Request $request)
    {
        $searchTerm = $request->get('q', '');
        
        if (empty($searchTerm)) {
            return response()->json([
                'search_term' => $searchTerm,
                'count' => 0,
                'results' => []
            ]);
        }
        
        $query = Item::with(self::ITEM_RELATIONS)
            ->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        
        $this->buildVisibilityQuery($query);
        
        $results = $query->limit(10)->get();
        
        return response()->json([
            'search_term' => $searchTerm,
            'count' => $results->count(),
            'results' => $results
        ]);
    }

    /**
     * 获取用户的物品分类
     */
    public function categories()
    {
        return response()->json(ItemCategory::where('user_id', Auth::id())->get());
    }

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
     * 应用分类过滤器
     */
    private function applyCategoryFilter($query, $value)
    {
        if ($value === 'uncategorized' || $value === null) {
            return $query->whereNull('category_id');
        }
        
        $category = ItemCategory::find($value);
        
        if (!$category) {
            return $query->where('category_id', $value);
        }
        
        if ($category->isParent()) {
            $childCategoryIds = $category->children()->pluck('id')->toArray();
            $allCategoryIds = array_merge([$value], $childCategoryIds);
            return $query->whereIn('category_id', $allCategoryIds);
        }
        
        return $query->where('category_id', $value);
    }

    /**
     * 处理物品图片（创建时）
     */
    private function processItemImages(Request $request, Item $item): void
    {
        if ($request->hasFile('images')) {
            $this->imageUploadService->processUploadedImages($request->file('images'), $item);
        }
        
        if ($request->has('image_paths')) {
            $this->imageUploadService->processImagePaths($request->image_paths, $item);
        }
    }

    /**
     * 处理物品图片更新
     */
    private function processItemImageUpdates(Request $request, Item $item): void
    {
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
    }

    /**
     * 处理标签
     */
    private function handleTags(Request $request, Item $item): void
    {
        if ($request->has('tags')) {
            $item->tags()->sync($request->tags ?? []);
        }
    }
}
