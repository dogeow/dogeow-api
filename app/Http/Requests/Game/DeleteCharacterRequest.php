<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class DeleteCharacterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'character_id' => 'required|integer|min:1|exists:game_characters,id',
        ];
    }

    public function messages(): array
    {
        return [
            'character_id.required' => '角色ID不能为空',
            'character_id.min' => '角色ID必须大于0',
            'character_id.exists' => '角色不存在',
        ];
    }
}
