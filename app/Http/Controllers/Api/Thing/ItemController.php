<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\ItemRequest;
use App\Jobs\TriggerKnowledgeIndexBuildJob;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\Log;

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

        return $query->jsonPaginate();
    }

    /**
     * 构建可见性查询条件
     */
    private function buildVisibilityQuery($query): void
    {
        if (Auth::check()) {
            $query->where(fn($q) => $q->where('is_public', true)->orWhere('user_id', Auth::id()));
            return;
        }

        $query->where('is_public', true);
    }

    /**
     * 获取请求限制
     */
    private function getRequestLimit(Request $request, int $default = self::DEFAULT_PAGE_SIZE): int
    {
        return (int) $request->get('limit', $default);
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

            TriggerKnowledgeIndexBuildJob::dispatch();

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

            TriggerKnowledgeIndexBuildJob::dispatch();

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
     * 增强搜索物品功能
     * 支持搜索名称、描述、标签、分类
     * 记录搜索历史
     */
    public function search(Request $request)
    {
        $searchTerm = $request->get('q', '');
        $limit = $this->getRequestLimit($request);
        
        if (empty($searchTerm)) {
            return response()->json([
                'search_term' => $searchTerm,
                'count' => 0,
                'results' => []
            ]);
        }
        
        $query = $this->buildSearchQuery($searchTerm);
        
        $this->buildVisibilityQuery($query);
        
        $results = $query->limit($limit)->get();
        
        // 记录搜索历史
        $this->recordSearchHistory($searchTerm, $results->count(), $request);
        
        return response()->json([
            'search_term' => $searchTerm,
            'count' => $results->count(),
            'results' => $results
        ]);
    }

    /**
     * 获取搜索建议（基于搜索历史）
     */
    public function searchSuggestions(Request $request)
    {
        $query = $request->get('q', '');
        $limit = $this->getRequestLimit($request, 5);
        
        if (empty($query)) {
            return response()->json([]);
        }
        
        // 基于用户搜索历史的建议
        $suggestions = DB::table('thing_search_history')
            ->select('search_term', DB::raw('COUNT(*) as frequency'))
            ->where('search_term', 'like', "%{$query}%")
            ->when(Auth::check(), function($q) {
                return $q->where('user_id', Auth::id());
            })
            ->groupBy('search_term')
            ->orderBy('frequency', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->pluck('search_term');
        
        return response()->json($suggestions);
    }

    /**
     * 获取搜索历史
     */
    public function searchHistory(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([]);
        }
        
        $limit = $this->getRequestLimit($request);
        
        $history = DB::table('thing_search_history')
            ->select('search_term', DB::raw('MAX(created_at) as last_searched'), DB::raw('COUNT(*) as search_count'))
            ->where('user_id', Auth::id())
            ->groupBy('search_term')
            ->orderBy('last_searched', 'desc')
            ->limit($limit)
            ->get();
        
        return response()->json($history);
    }

    /**
     * 清除搜索历史
     */
    public function clearSearchHistory(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => '未登录'], 401);
        }
        
        DB::table('thing_search_history')
            ->where('user_id', Auth::id())
            ->delete();
        
        return response()->json(['message' => '搜索历史已清除']);
    }

    /**
     * 记录搜索历史
     */
    private function recordSearchHistory(string $searchTerm, int $resultsCount, Request $request): void
    {
        try {
            DB::table('thing_search_history')->insert([
                'user_id' => Auth::id(),
                'search_term' => $searchTerm,
                'results_count' => $resultsCount,
                'filters' => json_encode($request->except(['q', 'limit'])),
                'ip_address' => $request->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // 记录失败不影响主要功能
            Log::warning('记录搜索历史失败: ' . $e->getMessage());
        }
    }

    /**
     * 构建增强搜索查询
     */
    private function buildSearchQuery(string $searchTerm)
    {
        return Item::with(self::ITEM_RELATIONS)
            ->where(function ($q) use ($searchTerm) {
                // 搜索名称和描述
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%")
                  // 搜索分类名称
                  ->orWhereHas('category', function($subQ) use ($searchTerm) {
                      $subQ->where('name', 'like', "%{$searchTerm}%");
                  })
                  // 搜索标签名称
                  ->orWhereHas('tags', function($subQ) use ($searchTerm) {
                      $subQ->where('name', 'like', "%{$searchTerm}%");
                  })
                  // 搜索位置信息
                  ->orWhereHas('spot', function($subQ) use ($searchTerm) {
                      $subQ->where('name', 'like', "%{$searchTerm}%");
                  })
                  ->orWhereHas('spot.room', function($subQ) use ($searchTerm) {
                      $subQ->where('name', 'like', "%{$searchTerm}%");
                  })
                  ->orWhereHas('spot.room.area', function($subQ) use ($searchTerm) {
                      $subQ->where('name', 'like', "%{$searchTerm}%");
                  });
            });
    }

    /**
     * 获取物品的关联列表
     */
    public function relations(Item $item)
    {
        if (!$this->canViewItem($item)) {
            return response()->json(['message' => '无权查看此物品'], 403);
        }
        
        $relations = [
            'related_items' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get(),
            'relating_items' => $item->relatingItems()->with(self::ITEM_RELATIONS)->get(),
        ];
        
        return response()->json($relations);
    }

    /**
     * 添加物品关联
     */
    public function addRelation(Request $request, Item $item)
    {
        if (!$this->canModifyItem($item)) {
            return response()->json(['message' => '无权修改此物品'], 403);
        }
        
        $request->validate([
            'related_item_id' => 'required|exists:thing_items,id',
            'relation_type' => 'required|in:accessory,replacement,related,bundle,parent,child',
            'description' => 'nullable|string|max:500',
        ]);
        
        $relatedItemId = $request->input('related_item_id');
        
        // 检查不能关联自己
        if ($item->id === $relatedItemId) {
            return response()->json(['message' => '不能关联自己'], 400);
        }
        
        // 检查关联的物品是否存在且有权限访问
        $relatedItem = Item::find($relatedItemId);
        if (!$this->canViewItem($relatedItem)) {
            return response()->json(['message' => '无权访问关联的物品'], 403);
        }
        
        try {
            $item->addRelation(
                $relatedItemId,
                $request->input('relation_type', 'related'),
                $request->input('description')
            );
            
            return response()->json([
                'message' => '关联添加成功',
                'relations' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get()
            ], 201);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                return response()->json(['message' => '该关联已存在'], 400);
            }
            
            return response()->json(['message' => '添加关联失败: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 删除物品关联
     */
    public function removeRelation(Item $item, int $relatedItemId)
    {
        if (!$this->canModifyItem($item)) {
            return response()->json(['message' => '无权修改此物品'], 403);
        }
        
        $item->removeRelation($relatedItemId);
        
        return response()->json([
            'message' => '关联删除成功',
            'relations' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get()
        ]);
    }

    /**
     * 批量添加关联
     */
    public function batchAddRelations(Request $request, Item $item)
    {
        if (!$this->canModifyItem($item)) {
            return response()->json(['message' => '无权修改此物品'], 403);
        }
        
        $request->validate([
            'relations' => 'required|array',
            'relations.*.related_item_id' => 'required|exists:thing_items,id',
            'relations.*.relation_type' => 'required|in:accessory,replacement,related,bundle,parent,child',
            'relations.*.description' => 'nullable|string|max:500',
        ]);
        
        $successCount = 0;
        $errors = [];
        
        foreach ($request->input('relations') as $relation) {
            try {
                $item->addRelation(
                    $relation['related_item_id'],
                    $relation['relation_type'],
                    $relation['description'] ?? null
                );
                $successCount++;
            } catch (\Exception $e) {
                $errors[] = [
                    'related_item_id' => $relation['related_item_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return response()->json([
            'message' => "成功添加 {$successCount} 个关联",
            'success_count' => $successCount,
            'errors' => $errors,
            'relations' => $item->relatedItems()->with(self::ITEM_RELATIONS)->get()
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
        $this->syncImagesByIds($request, $item);
        $this->processImagePathsUpdate($request, $item);
        $this->processImageOrderUpdate($request, $item);
        $this->processPrimaryImageUpdate($request, $item);
        $this->processImageDeletes($request, $item);
    }

    /**
     * 同步图片集合（保留 image_ids，其余删除）
     */
    private function syncImagesByIds(Request $request, Item $item): void
    {
        if (!$request->has('image_ids')) {
            return;
        }

        $keepIds = $request->input('image_ids', []);
        $allIds = $item->images()->pluck('id')->toArray();
        $deleteIds = array_diff($allIds, $keepIds);

        if (!empty($deleteIds)) {
            $this->imageUploadService->deleteImagesByIds($deleteIds, $item);
        }
    }

    /**
     * 处理图片路径更新
     */
    private function processImagePathsUpdate(Request $request, Item $item): void
    {
        if ($request->has('image_paths')) {
            $this->imageUploadService->processImagePaths($request->image_paths, $item);
        }
    }

    /**
     * 处理图片排序更新
     */
    private function processImageOrderUpdate(Request $request, Item $item): void
    {
        if ($request->has('image_order')) {
            $this->imageUploadService->updateImageOrder($request->image_order, $item);
        }
    }

    /**
     * 处理主图更新
     */
    private function processPrimaryImageUpdate(Request $request, Item $item): void
    {
        if ($request->has('primary_image_id')) {
            $this->imageUploadService->setPrimaryImage($request->primary_image_id, $item);
        }
    }

    /**
     * 处理图片删除
     */
    private function processImageDeletes(Request $request, Item $item): void
    {
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
