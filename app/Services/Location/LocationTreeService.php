<?php

namespace App\Services\Location;

use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class LocationTreeService extends BaseService
{
    /**
     * 构建位置树形结构
     *
     * @param int $userId
     * @return array
     */
    public function buildLocationTree(int $userId): array
    {
        // 获取当前用户的所有区域
        $areas = Area::where('user_id', $userId)
            ->withCount('rooms')
            ->get();

        // 获取当前用户的所有房间
        $rooms = Room::where('user_id', $userId)
            ->with('area')
            ->withCount('spots')
            ->get();

        // 获取当前用户的所有具体位置
        $spots = Spot::where('user_id', $userId)
            ->with('room')
            ->withCount('items')
            ->get();

        // 获取物品数量统计（使用 Eloquent 聚合）
        $itemCounts = $this->getItemCounts($userId);

        // 构建树形结构
        $tree = $this->buildTreeStructure($areas, $rooms, $spots, $itemCounts);

        return [
            'tree' => $tree,
            'areas' => $areas,
            'rooms' => $rooms,
            'spots' => $spots,
        ];
    }

    /**
     * 获取物品数量统计
     *
     * @param int $userId
     * @return array
     */
    private function getItemCounts(int $userId): array
    {
        // 使用 Eloquent 关系查询替代 DB::table
        $areaItemCounts = DB::table('thing_items')
            ->where('user_id', $userId)
            ->whereNotNull('area_id')
            ->select('area_id', DB::raw('SUM(quantity) as items_count'))
            ->groupBy('area_id')
            ->pluck('items_count', 'area_id')
            ->toArray();

        $roomItemCounts = DB::table('thing_items')
            ->where('user_id', $userId)
            ->whereNotNull('room_id')
            ->select('room_id', DB::raw('SUM(quantity) as items_count'))
            ->groupBy('room_id')
            ->pluck('items_count', 'room_id')
            ->toArray();

        return [
            'areas' => $areaItemCounts,
            'rooms' => $roomItemCounts,
        ];
    }

    /**
     * 构建树形结构
     *
     * @param \Illuminate\Database\Eloquent\Collection $areas
     * @param \Illuminate\Database\Eloquent\Collection $rooms
     * @param \Illuminate\Database\Eloquent\Collection $spots
     * @param array $itemCounts
     * @return array
     */
    private function buildTreeStructure($areas, $rooms, $spots, array $itemCounts): array
    {
        $tree = [];

        foreach ($areas as $area) {
            $areaNode = [
                'id' => 'area_' . $area->id,
                'name' => $area->name,
                'type' => 'area',
                'original_id' => $area->id,
                'children' => [],
                'items_count' => $itemCounts['areas'][$area->id] ?? 0,
            ];

            // 添加该区域下的房间
            $areaRooms = $rooms->where('area_id', $area->id);
            foreach ($areaRooms as $room) {
                $roomNode = [
                    'id' => 'room_' . $room->id,
                    'name' => $room->name,
                    'type' => 'room',
                    'original_id' => $room->id,
                    'parent_id' => $area->id,
                    'children' => [],
                    'items_count' => $itemCounts['rooms'][$room->id] ?? 0,
                ];

                // 添加该房间下的具体位置
                $roomSpots = $spots->where('room_id', $room->id);
                foreach ($roomSpots as $spot) {
                    $spotNode = [
                        'id' => 'spot_' . $spot->id,
                        'name' => $spot->name,
                        'type' => 'spot',
                        'original_id' => $spot->id,
                        'parent_id' => $room->id,
                        'items_count' => $spot->items_count,
                    ];

                    $roomNode['children'][] = $spotNode;
                }

                $areaNode['children'][] = $roomNode;
            }

            $tree[] = $areaNode;
        }

        return $tree;
    }
}
