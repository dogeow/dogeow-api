<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameMapDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /**
     * 获取所有地图
     */
    public function index(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        $maps = GameMapDefinition::query()
            ->where('is_active', true)
            ->orderBy('act')
            ->orderBy('min_level')
            ->get();

        $progress = $character->mapProgress()
            ->with('map')
            ->get()
            ->keyBy('map_id');

        return $this->success([
            'maps' => $maps,
            'progress' => $progress,
            'current_map_id' => $character->current_map_id,
        ]);
    }

    /**
     * 进入地图
     */
    public function enter(Request $request, int $mapId): JsonResponse
    {
        $character = $this->getCharacter($request);
        $map = GameMapDefinition::findOrFail($mapId);

        // 检查等级要求
        if (! $map->canEnter($character->level)) {
            return $this->error("需要等级 {$map->min_level} 才能进入该地图");
        }

        // 确保地图进度记录存在（首次进入时创建）
        $progress = $character->mapProgress()->where('map_id', $mapId)->first();

        if (! $progress) {
            $character->mapProgress()->create([
                'map_id' => $mapId,
            ]);
        }

        // 更新当前地图并自动开始战斗
        $character->current_map_id = $mapId;
        $character->is_fighting = true;  // 进入地图自动开始战斗
        $character->save();

        return $this->success([
            'character' => $character->fresh('currentMap'),
            'map' => $map,
        ], "已进入 {$map->name}");
    }

    /**
     * 传送到地图
     */
    public function teleport(Request $request, int $mapId): JsonResponse
    {
        $character = $this->getCharacter($request);
        $map = GameMapDefinition::findOrFail($mapId);

        // 检查等级要求
        if (! $map->canEnter($character->level)) {
            return $this->error("需要等级 {$map->min_level} 才能传送到该地图");
        }

        // 检查金币
        if ($character->gold < $map->teleport_cost) {
            return $this->error("金币不足，传送需要 {$map->teleport_cost} 金币");
        }

        // 扣除金币并传送，自动开始战斗
        $character->gold -= $map->teleport_cost;
        $character->current_map_id = $mapId;
        $character->is_fighting = true;  // 传送后自动开始战斗
        $character->save();

        return $this->success([
            'character' => $character->fresh('currentMap'),
            'gold_cost' => $map->teleport_cost,
        ], "已传送到 {$map->name}");
    }

    /**
     * 解锁地图（已废弃 - 地图无需解锁）
     *
     * @deprecated 地图系统不再需要解锁机制
     */
    public function unlock(Request $request, int $mapId): JsonResponse
    {
        // 地图无需解锁，直接返回成功
        $map = GameMapDefinition::findOrFail($mapId);

        return $this->success([], "地图 {$map->name} 无需解锁");
    }

    /**
     * 获取当前地图信息
     */
    public function current(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        if (! $character->current_map_id) {
            return $this->success([
                'current_map' => null,
                'monsters' => [],
            ]);
        }

        $map = $character->currentMap;
        $monsters = $map ? $map->getMonsters() : [];

        return $this->success([
            'current_map' => $map,
            'monsters' => $monsters,
            'is_fighting' => $character->is_fighting,
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
}
