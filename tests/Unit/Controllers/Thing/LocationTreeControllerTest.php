<?php

namespace Tests\Unit\Controllers\Thing;

use App\Http\Controllers\Api\Thing\LocationTreeController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for LocationTreeController
 *
 * @group location
 * @group stubs
 */
class LocationTreeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected LocationTreeController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new LocationTreeController;
    }

    /**
     * @test
     */
    public function index_returns_location_tree(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function tree_requires_authentication(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
