<?php

namespace App\Http\Requests\Thing;

use App\Models\Thing\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()?->id;
        $ownedUploadPathPattern = '~^uploads/' . preg_quote((string) $userId, '~') . '/[^/]+$~';
        $item = $this->route('item');
        $itemId = $item instanceof Item ? $item->id : (is_numeric($item) ? (int) $item : null);

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'status' => 'nullable|string|in:active,inactive,expired',
            'expiry_date' => 'nullable|date',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric|min:0',
            'category_id' => [
                'nullable',
                Rule::exists('thing_item_categories', 'id')->where(static fn ($query) => $query->where('user_id', $userId)),
            ],
            'area_id' => [
                'nullable',
                Rule::exists('thing_areas', 'id')->where(static fn ($query) => $query->where('user_id', $userId)),
            ],
            'room_id' => [
                'nullable',
                Rule::exists('thing_rooms', 'id')->where(static fn ($query) => $query->where('user_id', $userId)),
            ],
            'spot_id' => [
                'nullable',
                Rule::exists('thing_spots', 'id')->where(static fn ($query) => $query->where('user_id', $userId)),
            ],
            'is_public' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'image_paths' => 'nullable|array',
            'image_paths.*' => [
                'string',
                static function (string $attribute, mixed $value, \Closure $fail) use ($ownedUploadPathPattern): void {
                    if (! is_string($value) || preg_match($ownedUploadPathPattern, $value) !== 1) {
                        $fail('只能使用当前用户上传目录中的图片');

                        return;
                    }

                    $filename = pathinfo($value, PATHINFO_FILENAME);
                    if (str_ends_with($filename, '-origin') || str_ends_with($filename, '-thumb')) {
                        $fail('只能使用上传返回的图片路径');
                    }
                },
            ],
            'tags' => 'nullable|array',
            'tags.*' => [
                'integer',
                Rule::exists('thing_tags', 'id')->where(static fn ($query) => $query->where('user_id', $userId)),
            ],
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => [
                'integer',
                Rule::exists('thing_tags', 'id')->where(static fn ($query) => $query->where('user_id', $userId)),
            ],
            'image_ids' => 'nullable|array',
            'image_ids.*' => [
                'integer',
                Rule::exists('thing_item_images', 'id')->where(static fn ($query) => $query->where('item_id', $itemId)),
            ],
            'image_order' => 'nullable|array',
            'image_order.*' => [
                'integer',
                Rule::exists('thing_item_images', 'id')->where(static fn ($query) => $query->where('item_id', $itemId)),
            ],
            'primary_image_id' => [
                'nullable',
                'integer',
                Rule::exists('thing_item_images', 'id')->where(static fn ($query) => $query->where('item_id', $itemId)),
            ],
            'delete_images' => 'nullable|array',
            'delete_images.*' => [
                'integer',
                Rule::exists('thing_item_images', 'id')->where(static fn ($query) => $query->where('item_id', $itemId)),
            ],
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            'name.required' => '物品名称不能为空',
            'name.max' => '物品名称不能超过 255 个字符',
            'quantity.required' => '物品数量不能为空',
            'quantity.integer' => '物品数量必须为整数',
            'quantity.min' => '物品数量必须大于 0',
            'purchase_price.numeric' => '购买价格必须为数字',
            'purchase_price.min' => '购买价格不能为负数',
            'category_id.exists' => '所选分类不存在',
            'area_id.exists' => '所选区域不存在',
            'room_id.exists' => '所选房间不存在',
            'spot_id.exists' => '所选位置不存在',
            'tags.*.exists' => '所选标签不存在',
            'tag_ids.*.exists' => '所选标签不存在',
            'image_ids.*.exists' => '所选图片不存在',
            'image_order.*.exists' => '所选图片排序项不存在',
            'primary_image_id.exists' => '所选主图不存在',
            'delete_images.*.exists' => '所选待删除图片不存在',
            'images.*.image' => '上传的文件必须是图片',
            'images.*.mimes' => '图片格式必须为 jpeg,png,jpg,gif',
            'images.*.max' => '图片大小不能超过 2MB',
            'image_paths.*.string' => '图片路径必须是字符串',
        ];
    }
}
