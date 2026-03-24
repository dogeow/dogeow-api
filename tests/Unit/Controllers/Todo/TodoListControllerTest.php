<?php

namespace Tests\Unit\Controllers\Todo;

use App\Http\Controllers\Api\Todo\TodoListController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for TodoListController
 *
 * @group todo
 * @group stubs
 */
class TodoListControllerTest extends TestCase
{
    use RefreshDatabase;

    protected TodoListController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TodoListController;
    }

    /**
     * @test
     */
    public function index_returns_todo_lists_for_user(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function store_creates_new_todo_list(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function update_modifies_todo_list(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function destroy_deletes_todo_list(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function todo_lists_require_authentication(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
