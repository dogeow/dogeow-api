<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\LocationRequest;
use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\Thing\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    /**
     * 获取区域列表
     */
    public function areaIndex()
    {
        $areas = Area::where('user_id', Auth::id())
            ->withCount('rooms')
            ->get();
        
        return response()->json($areas);
    }

    /**
     * 存储新创建的区域
     */
    public function areaStore(LocationRequest $request)
    {
        $area = new Area($request->validated());
        $area->user_id = Auth::id();
        $area->save();
        
        return response()->json([
            'message' => '区域创建成功',
            'area' => $area
        ], 201);
    }

    /**
     * 显示指定区域
     */
    public function areaShow(Area $area)
    {
        // 检查权限：只有区域所有者可以查看
        if ($area->user_id !== Auth::id()) {
            return response()->json(['message' => '无权查看此区域'], 403);
        }
        
        return response()->json($area->load('rooms'));
    }

    /**
     * 更新指定区域
     */
    public function areaUpdate(LocationRequest $request, Area $area)
    {
        // 检查权限：只有区域所有者可以更新
        if ($area->user_id !== Auth::id()) {
            return response()->json(['message' => '无权更新此区域'], 403);
        }
        
        $area->update($request->validated());
        
        return response()->json([
            'message' => '区域更新成功',
            'area' => $area
        ]);
    }

    /**
     * 删除指定区域
     */
    public function areaDestroy(Area $area)
    {
        // 检查权限：只有区域所有者可以删除
        if ($area->user_id !== Auth::id()) {
            return response()->json(['message' => '无权删除此区域'], 403);
        }
        
        // 检查区域是否有关联的房间
        if ($area->rooms()->count() > 0) {
            return response()->json(['message' => '无法删除已有房间的区域'], 400);
        }
        
        $area->delete();
        
        return response()->json(['message' => '区域删除成功']);
    }

    /**
     * 获取房间列表
     */
    public function roomIndex(Request $request)
    {
        $query = Room::where('user_id', Auth::id())
            ->with('area')
            ->withCount('spots');
        
        // 如果指定了区域ID，则只获取该区域下的房间
        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }
        
        $rooms = $query->get();
        
        return response()->json($rooms);
    }

    /**
     * 存储新创建的房间
     */
    public function roomStore(LocationRequest $request)
    {
        // 检查区域是否属于当前用户
        $area = Area::findOrFail($request->area_id);
        if ($area->user_id !== Auth::id()) {
            return response()->json(['message' => '无权在此区域创建房间'], 403);
        }
        
        $room = new Room($request->validated());
        $room->user_id = Auth::id();
        $room->save();
        
        return response()->json([
            'message' => '房间创建成功',
            'room' => $room
        ], 201);
    }

    /**
     * 显示指定房间
     */
    public function roomShow(Room $room)
    {
        // 检查权限：只有房间所有者可以查看
        if ($room->user_id !== Auth::id()) {
            return response()->json(['message' => '无权查看此房间'], 403);
        }
        
        return response()->json($room->load(['area', 'spots']));
    }

    /**
     * 更新指定房间
     */
    public function roomUpdate(LocationRequest $request, Room $room)
    {
        // 检查权限：只有房间所有者可以更新
        if ($room->user_id !== Auth::id()) {
            return response()->json(['message' => '无权更新此房间'], 403);
        }
        
        // 如果更改了区域，检查新区域是否属于当前用户
        if ($request->has('area_id') && $request->area_id != $room->area_id) {
            $area = Area::findOrFail($request->area_id);
            if ($area->user_id !== Auth::id()) {
                return response()->json(['message' => '无权将房间移动到此区域'], 403);
            }
        }
        
        $room->update($request->validated());
        
        return response()->json([
            'message' => '房间更新成功',
            'room' => $room
        ]);
    }

    /**
     * 删除指定房间
     */
    public function roomDestroy(Room $room)
    {
        // 检查权限：只有房间所有者可以删除
        if ($room->user_id !== Auth::id()) {
            return response()->json(['message' => '无权删除此房间'], 403);
        }
        
        // 检查房间是否有关联的具体位置
        if ($room->spots()->count() > 0) {
            return response()->json(['message' => '无法删除已有具体位置的房间'], 400);
        }
        
        $room->delete();
        
        return response()->json(['message' => '房间删除成功']);
    }

    /**
     * 获取具体位置列表
     */
    public function spotIndex(Request $request)
    {
        $query = Spot::where('user_id', Auth::id())
            ->with('room.area')
            ->withCount('items');
        
        // 如果指定了房间ID，则只获取该房间下的具体位置
        if ($request->filled('room_id')) {
            $query->where('room_id', $request->room_id);
        }
        
        $spots = $query->get();
        
        return response()->json($spots);
    }

    /**
     * 存储新创建的具体位置
     */
    public function spotStore(LocationRequest $request)
    {
        // 检查房间是否属于当前用户
        $room = Room::findOrFail($request->room_id);
        if ($room->user_id !== Auth::id()) {
            return response()->json(['message' => '无权在此房间创建具体位置'], 403);
        }
        
        $spot = new Spot($request->validated());
        $spot->user_id = Auth::id();
        $spot->save();
        
        return response()->json([
            'message' => '具体位置创建成功',
            'spot' => $spot
        ], 201);
    }

    /**
     * 显示指定具体位置
     */
    public function spotShow(Spot $spot)
    {
        // 检查权限：只有具体位置所有者可以查看
        if ($spot->user_id !== Auth::id()) {
            return response()->json(['message' => '无权查看此具体位置'], 403);
        }
        
        return response()->json($spot->load(['room.area', 'items']));
    }

    /**
     * 更新指定具体位置
     */
    public function spotUpdate(LocationRequest $request, Spot $spot)
    {
        // 检查权限：只有具体位置所有者可以更新
        if ($spot->user_id !== Auth::id()) {
            return response()->json(['message' => '无权更新此具体位置'], 403);
        }
        
        // 如果更改了房间，检查新房间是否属于当前用户
        if ($request->has('room_id') && $request->room_id != $spot->room_id) {
            $room = Room::findOrFail($request->room_id);
            if ($room->user_id !== Auth::id()) {
                return response()->json(['message' => '无权将具体位置移动到此房间'], 403);
            }
        }
        
        $spot->update($request->validated());
        
        return response()->json([
            'message' => '具体位置更新成功',
            'spot' => $spot
        ]);
    }

    /**
     * 删除指定具体位置
     */
    public function spotDestroy(Spot $spot)
    {
        // 检查权限：只有具体位置所有者可以删除
        if ($spot->user_id !== Auth::id()) {
            return response()->json(['message' => '无权删除此具体位置'], 403);
        }
        
        // 检查具体位置是否有关联的物品
        if ($spot->items()->count() > 0) {
            return response()->json(['message' => '无法删除已有物品的具体位置'], 400);
        }
        
        $spot->delete();
        
        return response()->json(['message' => '具体位置删除成功']);
    }

    /**
     * 获取指定区域下的房间列表
     */
    public function areaRooms(Area $area)
    {
        // 检查权限：只有区域所有者可以查看
        if ($area->user_id !== Auth::id()) {
            return response()->json(['message' => '无权查看此区域的房间'], 403);
        }
        
        $rooms = Room::where('area_id', $area->id)
            ->where('user_id', Auth::id())
            ->with('area')
            ->withCount('spots')
            ->get();
        
        return response()->json($rooms);
    }

    /**
     * 获取指定房间下的位置列表
     */
    public function roomSpots(Room $room)
    {
        // 检查权限：只有房间所有者可以查看
        if ($room->user_id !== Auth::id()) {
            return response()->json(['message' => '无权查看此房间的位置'], 403);
        }
        
        $spots = Spot::where('room_id', $room->id)
            ->where('user_id', Auth::id())
            ->with('room.area')
            ->withCount('items')
            ->get();
        
        return response()->json($spots);
    }

    /**
     * 获取树形结构的位置数据
     */
    public function locationTree()
    {
        // 获取当前用户的所有区域
        $areas = Area::where('user_id', Auth::id())
            ->withCount('rooms')
            ->get();
        
        // 获取当前用户的所有房间
        $rooms = Room::where('user_id', Auth::id())
            ->with('area')
            ->withCount('spots')
            ->get();
        
        // 获取当前用户的所有具体位置
        $spots = Spot::where('user_id', Auth::id())
            ->with('room')
            ->withCount('items')
            ->get();
        
        // 构建树形结构
        $tree = [];
        
        // 获取每个区域下物品数量
        $areaItemCounts = DB::table('thing_items')
            ->where('user_id', Auth::id())
            ->whereNotNull('area_id')
            ->select('area_id', DB::raw('SUM(quantity) as items_count'))
            ->groupBy('area_id')
            ->pluck('items_count', 'area_id')
            ->toArray();
        
        // 获取每个房间下物品数量
        $roomItemCounts = DB::table('thing_items')
            ->where('user_id', Auth::id())
            ->whereNotNull('room_id')
            ->select('room_id', DB::raw('SUM(quantity) as items_count'))
            ->groupBy('room_id')
            ->pluck('items_count', 'room_id')
            ->toArray();
        
        foreach ($areas as $area) {
            $areaNode = [
                'id' => 'area_' . $area->id,
                'name' => $area->name,
                'type' => 'area',
                'original_id' => $area->id,
                'children' => [],
                'items_count' => $areaItemCounts[$area->id] ?? 0
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
                    'items_count' => $roomItemCounts[$room->id] ?? 0
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
                        'items_count' => $spot->items_count
                    ];
                    
                    $roomNode['children'][] = $spotNode;
                }
                
                $areaNode['children'][] = $roomNode;
            }
            
            $tree[] = $areaNode;
        }
        
        return response()->json([
            'tree' => $tree,
            'areas' => $areas,
            'rooms' => $rooms,
            'spots' => $spots
        ]);
    }
}
