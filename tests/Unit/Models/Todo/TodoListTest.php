<?php

namespace Tests\Unit\Models\Todo;

use App\Models\Todo\TodoList;
use App\Models\Todo\TodoTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for TodoList model
 *
 * @group todo
 * @group stubs
 */
class TodoListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_belongs_to_a_user(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function it_has_many_tasks(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function tasks_are_ordered_by_position(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function it_can_be_created_with_valid_attributes(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function position_is_cast_to_integer(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
