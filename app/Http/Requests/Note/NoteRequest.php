<?php

namespace App\Http\Requests\Note;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NoteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tags' => ['sometimes', 'array', 'max:50'],
            'tags.*' => ['required', 'string', 'max:255'],
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'content_markdown' => 'nullable|string',
            'is_draft' => 'nullable|boolean',
            'slug' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'is_wiki' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tags' => '标签',
            'tags.*' => '标签名称',
            'title' => '笔记标题',
            'content' => '笔记内容',
            'content_markdown' => '笔记 Markdown 内容',
            'is_draft' => '草稿状态',
        ];
    }
}
