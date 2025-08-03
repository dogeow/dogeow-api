<?php

namespace App\Models\Thing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ItemCategory extends Model
{
    use HasFactory;

    protected $table = 'thing_item_categories';

    protected $fillable = [
        'name',
        'user_id',
        'parent_id',
    ];

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取父分类
     */
    public function parent()
    {
        return $this->belongsTo(ItemCategory::class, 'parent_id');
    }

    /**
     * 获取子分类
     */
    public function children()
    {
        return $this->hasMany(ItemCategory::class, 'parent_id');
    }

    /**
     * 判断是否为主分类（没有父分类）
     */
    public function isParent()
    {
        return is_null($this->parent_id);
    }

    /**
     * 判断是否为子分类（有父分类）
     */
    public function isChild()
    {
        return !is_null($this->parent_id);
    }
} 