<?php

namespace App\Http\Requests\Cloud;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file_ids' => ['required', 'array', 'min:1', 'max:500'],
            // 先验证整个批次的所有权，避免部分移动后仍向客户端报告成功。
            'file_ids.*' => ['required', 'integer', 'distinct', Rule::exists('cloud_files', 'id')->where('user_id', $this->user()->id)],
            'target_folder_id' => ['present', 'nullable', 'integer', 'exists:cloud_files,id'],
        ];
    }
}
