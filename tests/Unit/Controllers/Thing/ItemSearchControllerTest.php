<?php

namespace Tests\Unit\Controllers\Thing;

use App\Http\Controllers\Api\Thing\ItemSearchController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for ItemSearchController
 *
 * @group thing
 * @group stubs
 */
class ItemSearchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected ItemSearchController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new ItemSearchController;
    }

    /**
     * @test
     */
    public function search_returns_items_matching_query(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function search_respects_pagination(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function search_requires_authentication(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
