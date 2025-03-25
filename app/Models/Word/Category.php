<?php

namespace App\Models\Word;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'word_categories';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * 获取此分类下的所有单词书
     */
    public function books()
    {
        return $this->hasMany(Book::class, 'word_category_id');
    }
} 