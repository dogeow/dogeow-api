<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class EquipItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|integer|min:1|exists:game_items,id',
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => '物品ID不能为空',
            'item_id.min' => '物品ID必须大于0',
            'item_id.exists' => '物品不存在',
        ];
    }
}
