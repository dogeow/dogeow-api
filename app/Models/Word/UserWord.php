<?php

namespace App\Models\Word;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWord extends Model
{
    use HasFactory;

    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'user_words';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'word_id',
        'word_book_id',
        'status',
        'review_count',
        'correct_count',
        'wrong_count',
        'is_favorite',
        'last_review_at',
        'next_review_at',
        'personal_note',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'status' => 'integer',
        'review_count' => 'integer',
        'correct_count' => 'integer',
        'wrong_count' => 'integer',
        'is_favorite' => 'boolean',
        'last_review_at' => 'datetime',
        'next_review_at' => 'datetime',
    ];

    /**
     * 学习状态常量
     */
    const STATUS_NEW = 0;       // 未学习
    const STATUS_LEARNING = 1;  // 学习中
    const STATUS_MASTERED = 2;  // 已掌握
    const STATUS_DIFFICULT = 3; // 困难词

    /**
     * 获取所属用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 获取相关单词
     */
    public function word()
    {
        return $this->belongsTo(Word::class, 'word_id');
    }

    /**
     * 获取所属单词书
     */
    public function book()
    {
        return $this->belongsTo(Book::class, 'word_book_id');
    }
} 