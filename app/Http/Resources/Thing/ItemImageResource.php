<?php

namespace App\Http\Resources\Thing;

use App\Models\Thing\ItemImage;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array|Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        /** @var ItemImage $resource */
        $resource = $this->resource;

        return [
            'thumbnail_path' => $resource->thumbnail_path,
        ];
    }
}
