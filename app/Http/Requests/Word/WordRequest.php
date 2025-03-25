<?php

namespace App\Http\Requests\Word;

use Illuminate\Foundation\Http\FormRequest;

class WordRequest extends FormRequest
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
            'word_book_id' => 'required|exists:word_books,id',
            'content' => 'required|string|max:100',
            'phonetic_uk' => 'nullable|string|max:100',
            'phonetic_us' => 'nullable|string|max:100',
            'audio_uk' => 'nullable|string',
            'audio_us' => 'nullable|string',
            'explanation' => 'required|string',
            'example_sentences' => 'nullable|array',
            'example_sentences.*.sentence' => 'nullable|string',
            'example_sentences.*.translation' => 'nullable|string',
            'synonyms' => 'nullable|string',
            'antonyms' => 'nullable|string',
            'notes' => 'nullable|string',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'frequency' => 'nullable|integer|min:1|max:5',
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
            'word_book_id' => '单词书',
            'content' => '单词内容',
            'phonetic_uk' => '英式音标',
            'phonetic_us' => '美式音标',
            'audio_uk' => '英式发音音频',
            'audio_us' => '美式发音音频',
            'explanation' => '单词释义',
            'example_sentences' => '例句',
            'example_sentences.*.sentence' => '例句',
            'example_sentences.*.translation' => '例句翻译',
            'synonyms' => '同义词',
            'antonyms' => '反义词',
            'notes' => '笔记',
            'difficulty' => '难度',
            'frequency' => '常见度',
        ];
    }
} 