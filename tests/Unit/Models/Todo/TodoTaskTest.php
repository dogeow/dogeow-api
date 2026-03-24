<?php

namespace Tests\Unit\Models\Todo;

use App\Models\Todo\TodoList;
use App\Models\Todo\TodoTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for TodoTask model
 *
 * @group todo
 * @group stubs
 */
class TodoTaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_belongs_to_a_todo_list(): void
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
    public function is_completed_is_cast_to_boolean(): void
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
