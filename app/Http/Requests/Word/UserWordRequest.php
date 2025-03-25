<?php

namespace App\Http\Requests\Word;

use Illuminate\Foundation\Http\FormRequest;

class UserWordRequest extends FormRequest
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
            'word_id' => 'required|exists:words,id',
            'word_book_id' => 'required|exists:word_books,id',
            'status' => 'nullable|integer|min:0|max:3',
            'is_favorite' => 'nullable|boolean',
            'personal_note' => 'nullable|string',
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
            'word_id' => '单词',
            'word_book_id' => '单词书',
            'status' => '学习状态',
            'is_favorite' => '是否收藏',
            'personal_note' => '个人笔记',
        ];
    }
} 