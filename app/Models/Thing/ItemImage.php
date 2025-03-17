<?php

namespace App\Models\Thing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemImage extends Model
{
    use HasFactory;

    protected $table = 'thing_item_images';

    protected $fillable = [
        'item_id',
        'path',
        'thumbnail_path',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
} 