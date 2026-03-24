<?php

namespace Tests\Unit\Controllers\Thing;

use App\Http\Controllers\Api\Thing\LocationSpotController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for LocationSpotController
 *
 * @group location
 * @group stubs
 */
class LocationSpotControllerTest extends TestCase
{
    use RefreshDatabase;

    protected LocationSpotController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new LocationSpotController;
    }

    /**
     * @test
     */
    public function index_returns_spots_for_area(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function show_returns_spot_details(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function spots_require_authentication(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
