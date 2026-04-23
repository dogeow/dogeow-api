<?php

namespace App\Http\Requests\Todo;

use App\Models\Todo\TodoTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTodoTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $listId = (int) $this->route('id');
        $taskCount = TodoTask::query()
            ->where('todo_list_id', $listId)
            ->count();

        return [
            'task_ids' => ['required', 'array', 'size:' . $taskCount],
            'task_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('todo_tasks', 'id')->where(static fn ($query) => $query->where('todo_list_id', $listId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'task_ids' => '任务 ID 列表',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_ids.size' => '任务重排时必须提交该列表中的全部任务 ID。',
        ];
    }
}
