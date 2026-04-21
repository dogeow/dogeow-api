<?php

namespace App\Http\Requests\Word;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EstimateVocabularyRequest extends FormRequest
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
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.word_id' => ['required', 'integer', 'distinct', 'exists:words,id'],
            'answers.*.correct' => ['required', 'boolean'],
        ];
    }
}
