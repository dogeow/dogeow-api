<?php

namespace App\Models\Word;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Book extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'word_books';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'word_category_id',
        'name',
        'cover_image',
        'description',
        'total_words',
        'difficulty',
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
        'total_words' => 'integer',
        'difficulty' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * 获取所属分类
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'word_category_id');
    }

    /**
     * 获取此单词书中的所有单词
     */
    public function words()
    {
        return $this->hasMany(Word::class, 'word_book_id');
    }
} 