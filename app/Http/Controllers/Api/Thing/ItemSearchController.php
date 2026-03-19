<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Services\Thing\ItemSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ItemSearchController extends Controller
{
    private const SEARCH_HISTORY_LIMIT = 10;

    public function __construct(
        private readonly ItemSearchService $itemSearchService
    ) {}

    /**
     * Search items
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
            'category_id' => 'nullable|integer',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'page' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $userId = $request->user()->id;
        $query = $request->input('q');
        $categoryId = $request->input('category_id');
        $tags = $request->input('tags', []);
        $page = $request->input('page', 1);
        $limit = $request->input('limit', 20);

        // Save search history
        $this->saveSearchHistory($userId, $query);

        $results = $this->itemSearchService->search($query, [
            'category_id' => $categoryId,
            'tags' => $tags,
            'user_id' => $userId,
            'page' => $page,
            'limit' => $limit,
        ]);

        return response()->json($results);
    }

    /**
     * Search suggestions (autocomplete)
     */
    public function searchSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:50',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $query = $request->input('q');
        $limit = $request->input('limit', 10);

        $suggestions = $this->itemSearchService->getSuggestions($query, $limit);

        return response()->json(['suggestions' => $suggestions]);
    }

    /**
     * Get search history
     */
    public function searchHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $history = $this->itemSearchService->getSearchHistory($userId, self::SEARCH_HISTORY_LIMIT);

        return response()->json(['history' => $history]);
    }

    /**
     * Clear search history
     */
    public function clearSearchHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $this->itemSearchService->clearSearchHistory($userId);

        Log::info('Search history cleared', ['user_id' => $userId]);

        return response()->json(['message' => 'Search history cleared']);
    }

    /**
     * Save search query to history
     */
    private function saveSearchHistory(int $userId, string $query): void
    {
        $this->itemSearchService->saveSearchHistory($userId, $query);
    }
}
