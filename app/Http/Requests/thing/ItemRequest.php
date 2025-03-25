<?php

namespace App\Http\Requests\Thing;

use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'required|integer|min:1',
            'status' => 'nullable|string|in:active,inactive,expired',
            'expiry_date' => 'nullable|date',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'category_id' => 'nullable|exists:thing_item_categories,id',
            'area_id' => 'nullable|exists:thing_areas,id',
            'room_id' => 'nullable|exists:thing_rooms,id',
            'spot_id' => 'nullable|exists:thing_spots,id',
            'is_public' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            'name.required' => '物品名称不能为空',
            'name.max' => '物品名称不能超过255个字符',
            'quantity.required' => '物品数量不能为空',
            'quantity.integer' => '物品数量必须为整数',
            'quantity.min' => '物品数量必须大于0',
            'purchase_price.numeric' => '购买价格必须为数字',
            'purchase_price.min' => '购买价格不能为负数',
            'category_id.exists' => '所选分类不存在',
            'spot_id.exists' => '所选位置不存在',
            'images.*.image' => '上传的文件必须是图片',
            'images.*.mimes' => '图片格式必须为jpeg,png,jpg,gif',
            'images.*.max' => '图片大小不能超过2MB',
        ];
    }
}
