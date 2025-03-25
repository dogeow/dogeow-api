<?php

namespace App\Http\Requests\Word;

use Illuminate\Foundation\Http\FormRequest;

class BookRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'word_category_id' => 'required|exists:word_categories,id',
            'name' => 'required|string|max:100',
            'cover_image' => 'nullable|string',
            'description' => 'nullable|string',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
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
            'word_category_id' => '分类',
            'name' => '单词书名称',
            'cover_image' => '封面图片',
            'description' => '描述',
            'difficulty' => '难度',
            'sort_order' => '排序',
            'is_active' => '是否激活',
        ];
    }
} 