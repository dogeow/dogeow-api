<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Thing\LocationRequest;
use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Services\Location\LocationTreeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function __construct(
        private readonly LocationTreeService $locationTreeService
    ) {}

    /**
     * 授权检查辅助方法，返回 JsonResponse 或 null（授权成功时）
     */
    private function authorizeOrFail(string $ability, mixed $model, ?string $errorMessage = null): ?\Illuminate\Http\JsonResponse
    {
        try {
            $this->authorize($ability, $model);

            return null;
        } catch (AuthorizationException $e) {
            return $this->error($errorMessage ?? '无权执行此操作', [], 403);
        }
    }

    /**
     * 获取区域列表
     */
    public function areaIndex()
    {
        $areas = Area::where('user_id', Auth::id())
            ->withCount('rooms')
            ->get();

        return $this->success(['areas' => $areas], 'Areas retrieved successfully');
    }

    /**
     * 存储新创建的区域
     */
    public function areaStore(LocationRequest $request)
    {
        $area = new Area($request->validated());
        $area->user_id = Auth::id();
        $area->save();

        return $this->success(['area' => $area], '区域创建成功', 201);
    }

    /**
     * 显示指定区域
     */
    public function areaShow(Area $area)
    {
        if ($error = $this->authorizeOrFail('view', $area, '无权查看此区域')) {
            return $error;
        }

        return $this->success(['area' => $area->load('rooms')], 'Area retrieved successfully');
    }

    /**
     * 更新指定区域
     */
    public function areaUpdate(LocationRequest $request, Area $area)
    {
        if ($error = $this->authorizeOrFail('update', $area, '无权更新此区域')) {
            return $error;
        }

        $area->update($request->validated());

        return $this->success(['area' => $area], '区域更新成功');
    }

    /**
     * 删除指定区域
     */
    public function areaDestroy(Area $area)
    {
        if ($error = $this->authorizeOrFail('delete', $area, '无权删除此区域')) {
            return $error;
        }

        // 检查区域是否有关联的房间
        if ($area->rooms()->count() > 0) {
            return $this->error('无法删除已有房间的区域', [], 400);
        }

        $area->delete();

        return $this->success([], '区域删除成功');
    }

    /**
     * 设置默认区域
     */
    public function setDefaultArea(Area $area)
    {
        if ($error = $this->authorizeOrFail('setDefault', $area, '无权设置此区域为默认')) {
            return $error;
        }

        // 使用事务确保数据一致性
        DB::transaction(function () use ($area) {
            // 先将该用户的所有区域设置为非默认
            Area::where('user_id', Auth::id())->update(['is_default' => false]);

            // 将指定区域设置为默认
            $area->update(['is_default' => true]);
        });

        return $this->success(['area' => $area], '默认区域设置成功');
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

        return $this->success(['rooms' => $rooms], 'Rooms retrieved successfully');
    }

    /**
     * 存储新创建的房间
     */
    public function roomStore(LocationRequest $request)
    {
        $area = Area::findOrFail($request->area_id);
        if ($error = $this->authorizeOrFail('createForArea', [Room::class, $area], '无权在此区域创建房间')) {
            return $error;
        }

        $room = new Room($request->validated());
        $room->user_id = Auth::id();
        $room->save();

        return $this->success(['room' => $room], '房间创建成功', 201);
    }

    /**
     * 显示指定房间
     */
    public function roomShow(Room $room)
    {
        if ($error = $this->authorizeOrFail('view', $room, '无权查看此房间')) {
            return $error;
        }

        return $this->success(['room' => $room->load(['area', 'spots'])], 'Room retrieved successfully');
    }

    /**
     * 更新指定房间
     */
    public function roomUpdate(LocationRequest $request, Room $room)
    {
        if ($error = $this->authorizeOrFail('update', $room, '无权更新此房间')) {
            return $error;
        }

        // 如果更改了区域，检查新区域是否属于当前用户
        if ($request->has('area_id') && $request->area_id != $room->area_id) {
            $area = Area::findOrFail($request->area_id);
            if ($error = $this->authorizeOrFail('moveToArea', [$room, $area], '无权将房间移动到此区域')) {
                return $error;
            }
        }

        $room->update($request->validated());

        return $this->success(['room' => $room], '房间更新成功');
    }

    /**
     * 删除指定房间
     */
    public function roomDestroy(Room $room)
    {
        if ($error = $this->authorizeOrFail('delete', $room, '无权删除此房间')) {
            return $error;
        }

        // 检查房间是否有关联的具体位置
        if ($room->spots()->count() > 0) {
            return $this->error('无法删除已有具体位置的房间', [], 400);
        }

        $room->delete();

        return $this->success([], '房间删除成功');
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

        return $this->success(['spots' => $spots], 'Spots retrieved successfully');
    }

    /**
     * 存储新创建的具体位置
     */
    public function spotStore(LocationRequest $request)
    {
        $room = Room::findOrFail($request->room_id);
        if ($error = $this->authorizeOrFail('createForRoom', [Spot::class, $room], '无权在此房间创建具体位置')) {
            return $error;
        }

        $spot = new Spot($request->validated());
        $spot->user_id = Auth::id();
        $spot->save();

        return $this->success(['spot' => $spot], '具体位置创建成功', 201);
    }

    /**
     * 显示指定具体位置
     */
    public function spotShow(Spot $spot)
    {
        if ($error = $this->authorizeOrFail('view', $spot, '无权查看此具体位置')) {
            return $error;
        }

        return $this->success(['spot' => $spot->load(['room.area', 'items'])], 'Spot retrieved successfully');
    }

    /**
     * 更新指定具体位置
     */
    public function spotUpdate(LocationRequest $request, Spot $spot)
    {
        if ($error = $this->authorizeOrFail('update', $spot, '无权更新此具体位置')) {
            return $error;
        }

        // 如果更改了房间，检查新房间是否属于当前用户
        if ($request->has('room_id') && $request->room_id != $spot->room_id) {
            $room = Room::findOrFail($request->room_id);
            if ($error = $this->authorizeOrFail('moveToRoom', [$spot, $room], '无权将具体位置移动到此房间')) {
                return $error;
            }
        }

        $spot->update($request->validated());

        return $this->success(['spot' => $spot], '具体位置更新成功');
    }

    /**
     * 删除指定具体位置
     */
    public function spotDestroy(Spot $spot)
    {
        if ($error = $this->authorizeOrFail('delete', $spot, '无权删除此具体位置')) {
            return $error;
        }

        // 检查具体位置是否有关联的物品
        if ($spot->items()->count() > 0) {
            return $this->error('无法删除已有物品的具体位置', [], 400);
        }

        $spot->delete();

        return $this->success([], '具体位置删除成功');
    }

    /**
     * 获取指定区域下的房间列表
     */
    public function areaRooms(Area $area)
    {
        if ($error = $this->authorizeOrFail('view', $area, '无权查看此区域的房间')) {
            return $error;
        }

        $rooms = Room::where('area_id', $area->id)
            ->where('user_id', Auth::id())
            ->with('area')
            ->withCount('spots')
            ->get();

        return $this->success(['rooms' => $rooms], 'Area rooms retrieved successfully');
    }

    /**
     * 获取指定房间下的位置列表
     */
    public function roomSpots(Room $room)
    {
        if ($error = $this->authorizeOrFail('view', $room, '无权查看此房间的位置')) {
            return $error;
        }

        $spots = Spot::where('room_id', $room->id)
            ->where('user_id', Auth::id())
            ->with('room.area')
            ->withCount('items')
            ->get();

        return $this->success(['spots' => $spots], 'Room spots retrieved successfully');
    }

    /**
     * 获取树形结构的位置数据
     */
    public function locationTree()
    {
        $result = $this->locationTreeService->buildLocationTree(Auth::id());

        return $this->success($result, 'Location tree retrieved successfully');
    }
}
