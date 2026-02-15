<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class MoveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|integer|exists:game_items,id',
            'to_storage' => 'required|boolean',
            'slot_index' => 'sometimes|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => '物品ID不能为空',
            'item_id.exists' => '物品不存在',
            'to_storage.required' => '目标位置不能为空',
        ];
    }
}
