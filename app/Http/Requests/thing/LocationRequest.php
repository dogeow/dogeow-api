<?php

namespace App\Http\Requests\Thing;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
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
        $rules = [
            'name' => 'required|string|max:255',
        ];

        // 根据请求路径添加不同的验证规则
        if ($this->is('*/rooms') || $this->is('*/rooms/*')) {
            $rules['area_id'] = 'required|exists:thing_areas,id';
        }

        if ($this->is('*/spots') || $this->is('*/spots/*')) {
            $rules['room_id'] = 'required|exists:thing_rooms,id';
        }

        // 对于更新操作，允许部分字段更新
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = [
                'name' => 'sometimes|required|string|max:255',
            ];

            if ($this->is('*/rooms/*')) {
                $rules['area_id'] = 'sometimes|required|exists:thing_areas,id';
            }

            if ($this->is('*/spots/*')) {
                $rules['room_id'] = 'sometimes|required|exists:thing_rooms,id';
            }
        }

        return $rules;
    }

    /**
     * Get the validation messages for the request.
     */
    public function messages(): array
    {
        return [
            'name.required' => '名称不能为空',
            'name.max' => '名称不能超过255个字符',
            'area_id.required' => '区域ID不能为空',
            'area_id.exists' => '所选区域不存在',
            'room_id.required' => '房间ID不能为空',
            'room_id.exists' => '所选房间不存在',
        ];
    }
}
