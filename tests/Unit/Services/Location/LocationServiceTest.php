<?php

namespace Tests\Unit\Services\Location;

use App\Models\Thing\Area;
use App\Models\Thing\Item;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\User;
use App\Services\Location\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationService;
    }

    public function test_build_location_tree_returns_tree_structure(): void
    {
        $user = User::factory()->create();

        $result = $this->service->buildLocationTree($user->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tree', $result);
        $this->assertArrayHasKey('areas', $result);
        $this->assertArrayHasKey('rooms', $result);
        $this->assertArrayHasKey('spots', $result);
        $this->assertIsArray($result['tree']);
    }

    public function test_build_location_tree_includes_areas(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'My Area', 'user_id' => $user->id]);

        $result = $this->service->buildLocationTree($user->id);

        $this->assertCount(1, $result['areas']);
        $this->assertSame('My Area', $result['areas']->first()->name);
    }

    public function test_build_location_tree_includes_rooms(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Area', 'user_id' => $user->id]);
        $room = Room::create(['name' => 'My Room', 'area_id' => $area->id, 'user_id' => $user->id]);

        $result = $this->service->buildLocationTree($user->id);

        $this->assertCount(1, $result['rooms']);
        $this->assertSame('My Room', $result['rooms']->first()->name);
    }

    public function test_build_location_tree_includes_spots(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Area', 'user_id' => $user->id]);
        $room = Room::create(['name' => 'Room', 'area_id' => $area->id, 'user_id' => $user->id]);
        $spot = Spot::create(['name' => 'My Spot', 'room_id' => $room->id, 'user_id' => $user->id]);

        $result = $this->service->buildLocationTree($user->id);

        $this->assertCount(1, $result['spots']);
        $this->assertSame('My Spot', $result['spots']->first()->name);
    }

    public function test_build_location_tree_calculates_area_item_counts(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Area', 'user_id' => $user->id]);
        Item::create(['name' => 'Item1', 'user_id' => $user->id, 'area_id' => $area->id, 'quantity' => 5]);
        Item::create(['name' => 'Item2', 'user_id' => $user->id, 'area_id' => $area->id, 'quantity' => 3]);

        $result = $this->service->buildLocationTree($user->id);

        $areaNode = $result['tree'][0];
        $this->assertSame(8, $areaNode['items_count']);
    }

    public function test_build_location_tree_calculates_room_item_counts(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Area', 'user_id' => $user->id]);
        $room = Room::create(['name' => 'Room', 'area_id' => $area->id, 'user_id' => $user->id]);
        Item::create(['name' => 'Item', 'user_id' => $user->id, 'area_id' => $area->id, 'room_id' => $room->id, 'quantity' => 10]);

        $result = $this->service->buildLocationTree($user->id);

        $roomNode = $result['tree'][0]['children'][0];
        $this->assertSame(10, $roomNode['items_count']);
    }

    public function test_build_location_tree_returns_empty_for_user_with_no_locations(): void
    {
        $user = User::factory()->create();

        $result = $this->service->buildLocationTree($user->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result['tree']);
        $this->assertEmpty($result['areas']);
        $this->assertEmpty($result['rooms']);
        $this->assertEmpty($result['spots']);
    }

    public function test_build_location_tree_nests_rooms_under_areas(): void
    {
        $user = User::factory()->create();
        $area1 = Area::create(['name' => 'Area 1', 'user_id' => $user->id]);
        $area2 = Area::create(['name' => 'Area 2', 'user_id' => $user->id]);
        $room1 = Room::create(['name' => 'Room in Area 1', 'area_id' => $area1->id, 'user_id' => $user->id]);
        Room::create(['name' => 'Room in Area 2', 'area_id' => $area2->id, 'user_id' => $user->id]);

        $result = $this->service->buildLocationTree($user->id);

        $this->assertCount(1, $result['tree'][0]['children']); // Area 1 has 1 room
        $this->assertSame('Room in Area 1', $result['tree'][0]['children'][0]['name']);
    }

    public function test_build_location_tree_nests_spots_under_rooms(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Area', 'user_id' => $user->id]);
        $room = Room::create(['name' => 'Room', 'area_id' => $area->id, 'user_id' => $user->id]);
        Spot::create(['name' => 'Spot 1', 'room_id' => $room->id, 'user_id' => $user->id]);
        Spot::create(['name' => 'Spot 2', 'room_id' => $room->id, 'user_id' => $user->id]);

        $result = $this->service->buildLocationTree($user->id);

        $roomNode = $result['tree'][0]['children'][0];
        $this->assertCount(2, $roomNode['children']);
    }

    public function test_build_location_tree_excludes_other_users_locations(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        Area::create(['name' => 'User1 Area', 'user_id' => $user1->id]);
        Area::create(['name' => 'User2 Area', 'user_id' => $user2->id]);

        $result = $this->service->buildLocationTree($user1->id);

        $this->assertCount(1, $result['areas']);
        $this->assertSame('User1 Area', $result['areas']->first()->name);
    }

    public function test_build_location_tree_spot_items_count_comes_from_spot(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Area', 'user_id' => $user->id]);
        $room = Room::create(['name' => 'Room', 'area_id' => $area->id, 'user_id' => $user->id]);
        $spot = Spot::create(['name' => 'Spot', 'room_id' => $room->id, 'user_id' => $user->id]);
        Item::create(['name' => 'Item', 'user_id' => $user->id, 'spot_id' => $spot->id, 'quantity' => 5]);

        $result = $this->service->buildLocationTree($user->id);

        $spotNode = $result['tree'][0]['children'][0]['children'][0];
        // items_count on spot comes from spots.items_count (withCount), which counts items not sum of quantity
        $this->assertSame(1, $spotNode['items_count']);
    }

    public function test_build_location_tree_area_node_has_correct_structure(): void
    {
        $user = User::factory()->create();
        $area = Area::create(['name' => 'Test Area', 'user_id' => $user->id]);

        $result = $this->service->buildLocationTree($user->id);

        $node = $result['tree'][0];
        $this->assertSame('area_' . $area->id, $node['id']);
        $this->assertSame('Test Area', $node['name']);
        $this->assertSame('area', $node['type']);
        $this->assertSame($area->id, $node['original_id']);
        $this->assertArrayHasKey('children', $node);
    }
}
