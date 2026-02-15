<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePotionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'auto_use_hp_potion' => 'nullable|boolean',
            'hp_potion_threshold' => 'nullable|integer|min:1|max:100',
            'auto_use_mp_potion' => 'nullable|boolean',
            'mp_potion_threshold' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'hp_potion_threshold.min' => 'HP药水阈值最小为1',
            'hp_potion_threshold.max' => 'HP药水阈值最大为100',
            'mp_potion_threshold.min' => 'MP药水阈值最小为1',
            'mp_potion_threshold.max' => 'MP药水阈值最大为100',
        ];
    }
}
