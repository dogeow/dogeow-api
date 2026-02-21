<?php

namespace App\Http\Resources\Word;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'difficulty' => $this->difficulty,
            'total_words' => $this->total_words,
            'sort_order' => $this->sort_order,
            'category' => $this->whenLoaded('category'),
            'education_levels' => $this->whenLoaded('educationLevels', function () {
                return $this->educationLevels->map(fn ($level) => [
                    'id' => $level->id,
                    'code' => $level->code,
                    'name' => $level->name,
                ]);
            }),
        ];
    }
}
