<?php

namespace App\Models\Thing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    use HasFactory, Searchable;

    protected $table = 'thing_items';

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'quantity',
        'status',
        'expiry_date',
        'purchase_date',
        'purchase_price',
        'category_id',
        'area_id',
        'room_id',
        'spot_id',
        'is_public',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2'
    ];

    protected $appends = [
        'thumbnail_url',
    ];

    /**
     * 获取缩略图URL
     */
    public function getThumbnailUrlAttribute()
    {
        // 优先使用主图片
        if ($this->primaryImage && $this->primaryImage->thumbnail_url) {
            return $this->primaryImage->thumbnail_url;
        }
        
        // 如果没有主图片，使用第一张图片
        $firstImage = $this->images()->first();
        if ($firstImage && $firstImage->thumbnail_url) {
            return $firstImage->thumbnail_url;
        }
        
        return null;
    }

    /**
     * 获取模型的可搜索数据
     *
     * @return array
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'is_public' => $this->is_public,
            'user_id' => $this->user_id,
        ];
    }

    /**
     * 自定义搜索查询
     * 
     * @param  string  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($builder, $query)
    {
        return $builder->where(function($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('description', 'like', "%{$query}%");
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(ItemImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ItemImage::class)->where('is_primary', true);
    }

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function spot()
    {
        return $this->belongsTo(Spot::class);
    }
    
    /**
     * 获取物品的标签
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'thing_item_tag', 'item_id', 'thing_tag_id')
            ->withTimestamps();
    }
} 