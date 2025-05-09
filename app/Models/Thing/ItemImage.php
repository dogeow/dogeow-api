<?php

namespace App\Models\Thing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    protected $appends = [
        'url',
        'thumbnail_url',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * 获取图片完整URL
     */
    public function getUrlAttribute()
    {
        if (!$this->path) {
            return null;
        }
        
        return config('app.url') . '/storage/' . $this->path;
    }

    /**
     * 获取缩略图完整URL
     */
    public function getThumbnailUrlAttribute()
    {
        if (!$this->thumbnail_path) {
            return null;
        }
        
        return config('app.url') . '/storage/' . $this->thumbnail_path;
    }
} 