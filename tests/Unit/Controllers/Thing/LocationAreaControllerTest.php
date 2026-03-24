<?php

namespace Tests\Unit\Controllers\Thing;

use App\Http\Controllers\Api\Thing\LocationAreaController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for LocationAreaController
 *
 * @group location
 * @group stubs
 */
class LocationAreaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected LocationAreaController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new LocationAreaController;
    }

    /**
     * @test
     */
    public function index_returns_areas(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function show_returns_area_details(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function areas_require_authentication(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
