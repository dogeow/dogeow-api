<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHomeLayoutRequest extends FormRequest
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
            'layout' => ['required', 'array'],
            'layout.tiles' => ['required', 'array'],
            'layout.tiles.*.name' => ['required', 'string'],
            'layout.tiles.*.size' => ['required', 'string', 'in:1x1,1x2,2x1,3x1'],
            'layout.tiles.*.order' => ['required', 'integer', 'min:0'],
        ];
    }
}
