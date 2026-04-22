<?php

namespace App\Http\Requests\Todo;

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

        return [
            'task_ids' => 'required|array',
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
}
