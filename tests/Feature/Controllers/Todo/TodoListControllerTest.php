<?php

namespace Tests\Feature\Controllers\Todo;

use App\Models\Todo\TodoList;
use App\Models\Todo\TodoTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TodoListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorder_tasks_updates_positions_in_given_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $list = TodoList::query()->create([
            'user_id' => $user->id,
            'name' => 'Work',
            'description' => null,
            'position' => 0,
        ]);

        $taskA = $this->createTask($list, 'Task A', 0);
        $taskB = $this->createTask($list, 'Task B', 1);
        $taskC = $this->createTask($list, 'Task C', 2);

        $response = $this->putJson("/api/todos/{$list->id}/tasks/reorder", [
            'task_ids' => [$taskC->id, $taskA->id, $taskB->id],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('todo_tasks', ['id' => $taskC->id, 'position' => 0]);
        $this->assertDatabaseHas('todo_tasks', ['id' => $taskA->id, 'position' => 1]);
        $this->assertDatabaseHas('todo_tasks', ['id' => $taskB->id, 'position' => 2]);
    }

    public function test_reorder_tasks_rejects_task_ids_from_another_list(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $targetList = TodoList::query()->create([
            'user_id' => $user->id,
            'name' => 'Target',
            'description' => null,
            'position' => 0,
        ]);
        $otherList = TodoList::query()->create([
            'user_id' => $user->id,
            'name' => 'Other',
            'description' => null,
            'position' => 1,
        ]);

        $validTask = $this->createTask($targetList, 'Valid', 0);
        $foreignTask = $this->createTask($otherList, 'Foreign', 0);

        $response = $this->putJson("/api/todos/{$targetList->id}/tasks/reorder", [
            'task_ids' => [$validTask->id, $foreignTask->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_ids.1']);
    }

    public function test_reorder_tasks_rejects_duplicate_task_ids(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $list = TodoList::query()->create([
            'user_id' => $user->id,
            'name' => 'Errands',
            'description' => null,
            'position' => 0,
        ]);

        $task = $this->createTask($list, 'Duplicate me', 0);

        $response = $this->putJson("/api/todos/{$list->id}/tasks/reorder", [
            'task_ids' => [$task->id, $task->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_ids.1']);
    }

    public function test_reorder_tasks_requires_all_task_ids_for_the_list(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $list = TodoList::query()->create([
            'user_id' => $user->id,
            'name' => 'Household',
            'description' => null,
            'position' => 0,
        ]);

        $taskA = $this->createTask($list, 'Task A', 0);
        $taskB = $this->createTask($list, 'Task B', 1);
        $taskC = $this->createTask($list, 'Task C', 2);

        $response = $this->putJson("/api/todos/{$list->id}/tasks/reorder", [
            'task_ids' => [$taskC->id, $taskA->id],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['task_ids']);

        $this->assertDatabaseHas('todo_tasks', ['id' => $taskA->id, 'position' => 0]);
        $this->assertDatabaseHas('todo_tasks', ['id' => $taskB->id, 'position' => 1]);
        $this->assertDatabaseHas('todo_tasks', ['id' => $taskC->id, 'position' => 2]);
    }

    private function createTask(TodoList $list, string $title, int $position): TodoTask
    {
        return TodoTask::query()->create([
            'todo_list_id' => $list->id,
            'title' => $title,
            'is_completed' => false,
            'position' => $position,
        ]);
    }
}
