<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemGem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GemController extends Controller
{
    /**
     * 镶嵌宝石到装备
     */
    public function socket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_items,id',
            'gem_item_id' => 'required|integer|exists:game_items,id',
            'socket_index' => 'required|integer|min:0',
        ]);

        $character = $this->getCharacter($request);

        // 获取装备和宝石
        $equipment = GameItem::where('id', $validated['item_id'])
            ->where('character_id', $character->id)
            ->firstOrFail();

        $gemItem = GameItem::where('id', $validated['gem_item_id'])
            ->where('character_id', $character->id)
            ->where('is_in_storage', false)
            ->firstOrFail();

        $gemDefinition = $gemItem->definition;

        // 验证是否为宝石
        if ($gemDefinition->type !== 'gem') {
            return $this->error('只能镶嵌宝石');
        }

        // 验证装备是否为装备类型
        $equipmentTypes = ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring', 'amulet'];
        if (! in_array($equipment->definition->type, $equipmentTypes)) {
            return $this->error('只能向装备镶嵌宝石');
        }

        // 验证插槽数量
        if ($equipment->sockets <= 0) {
            return $this->error('该装备没有宝石插槽');
        }

        // 验证插槽索引
        if ($validated['socket_index'] >= $equipment->sockets) {
            return $this->error('插槽索引超出范围');
        }

        // 检查该插槽是否已有宝石
        $existingGem = GameItemGem::where('item_id', $equipment->id)
            ->where('socket_index', $validated['socket_index'])
            ->first();

        if ($existingGem) {
            return $this->error('该插槽已有宝石，请先卸下');
        }

        // 镶嵌宝石
        GameItemGem::create([
            'item_id' => $equipment->id,
            'gem_definition_id' => $gemDefinition->id,
            'socket_index' => $validated['socket_index'],
        ]);

        // 删除宝石物品
        $gemItem->delete();

        return $this->success([
            'equipment' => $equipment->load('gems.gemDefinition'),
            'message' => '宝石镶嵌成功',
        ], '宝石镶嵌成功');
    }

    /**
     * 从装备卸下宝石
     */
    public function unsocket(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_items,id',
            'socket_index' => 'required|integer|min:0',
        ]);

        $character = $this->getCharacter($request);

        // 获取装备
        $equipment = GameItem::where('id', $validated['item_id'])
            ->where('character_id', $character->id)
            ->firstOrFail();

        // 查找宝石
        $gem = GameItemGem::where('item_id', $equipment->id)
            ->where('socket_index', $validated['socket_index'])
            ->first();

        if (! $gem) {
            return $this->error('该插槽没有宝石');
        }

        $gemDefinition = $gem->gemDefinition;

        // 检查背包空间
        $inventoryCount = $character->items()->where('is_in_storage', false)->count();
        if ($inventoryCount >= 50) { // INVENTORY_SIZE
            return $this->error('背包已满，无法卸下宝石');
        }

        // 找到空位
        $slotIndex = $this->findEmptySlot($character);

        // 创建宝石物品
        GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $gemDefinition->id,
            'quality' => 'common',
            'stats' => [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $slotIndex,
            'sockets' => 0,
        ]);

        // 删除镶嵌记录
        $gem->delete();

        return $this->success([
            'equipment' => $equipment->load('gems.gemDefinition'),
            'message' => '宝石卸下成功',
        ], '宝石卸下成功');
    }

    /**
     * 获取装备的宝石信息
     */
    public function getGems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_items,id',
        ]);

        $character = $this->getCharacter($request);

        $item = GameItem::where('id', $validated['item_id'])
            ->where('character_id', $character->id)
            ->with('gems.gemDefinition')
            ->firstOrFail();

        return $this->success([
            'item' => $item,
            'sockets' => $item->sockets,
            'socketed_gems' => $item->gems,
        ]);
    }

    /**
     * 获取角色
     */
    private function getCharacter(Request $request): GameCharacter
    {
        $characterId = $request->query('character_id') ?: $request->input('character_id');

        $query = GameCharacter::query()
            ->where('user_id', $request->user()->id);

        if ($characterId) {
            $query->where('id', $characterId);
        }

        return $query->firstOrFail();
    }

    /**
     * 查找空背包槽位
     */
    private function findEmptySlot(GameCharacter $character): int
    {
        $occupiedSlots = $character->items()
            ->where('is_in_storage', false)
            ->pluck('slot_index')
            ->filter()
            ->toArray();

        for ($i = 0; $i < 50; $i++) {
            if (! in_array($i, $occupiedSlots)) {
                return $i;
            }
        }

        return 0;
    }
}
