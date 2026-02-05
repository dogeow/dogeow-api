<?php

namespace App\Http\Resources\Word;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WordResource extends JsonResource
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
            'content' => $this->content,
            'phonetic_us' => $this->phonetic_us,
            'explanation' => $this->explanation,
            'example_sentences' => $this->example_sentences,
            'difficulty' => $this->difficulty,
            'frequency' => $this->frequency,
            'books' => $this->whenLoaded('books', fn() => BookResource::collection($this->books)),
            'education_levels' => $this->whenLoaded('educationLevels', function () {
                return $this->educationLevels->map(fn($level) => [
                    'id' => $level->id,
                    'code' => $level->code,
                    'name' => $level->name,
                ]);
            }),
        ];
    }
}
