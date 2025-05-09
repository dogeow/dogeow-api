<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoteTagRequest extends FormRequest
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
            'name' => 'required|string|max:50',
            'color' => 'sometimes|string|regex:/^#([A-Fa-f0-9]{6})$/'
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
            'name' => '标签名称',
            'color' => '标签颜色',
        ];
    }
}
