<?php

namespace App\Models\Thing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Laravel\Scout\Searchable;

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
} 