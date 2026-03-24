<?php

namespace Tests\Unit\Controllers\Thing;

use App\Http\Controllers\Api\Thing\LocationRoomController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for LocationRoomController
 *
 * @group location
 * @group stubs
 */
class LocationRoomControllerTest extends TestCase
{
    use RefreshDatabase;

    protected LocationRoomController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new LocationRoomController;
    }

    /**
     * @test
     */
    public function index_returns_rooms_for_location(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function show_returns_room_details(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function rooms_require_authentication(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
