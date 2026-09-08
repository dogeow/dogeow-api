<?php

namespace App\Http\Requests\Cloud;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['folder', 'image', 'pdf', 'document', 'spreadsheet', 'archive', 'audio', 'video', 'other'])],
            'sort_by' => ['sometimes', Rule::in(['name', 'size', 'created_at', 'updated_at'])],
            'sort_direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
