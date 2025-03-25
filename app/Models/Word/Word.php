<?php

namespace App\Models\Word;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Word extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'words';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'word_book_id',
        'content',
        'phonetic_uk',
        'phonetic_us',
        'audio_uk',
        'audio_us',
        'explanation',
        'example_sentences',
        'synonyms',
        'antonyms',
        'notes',
        'difficulty',
        'frequency',
    ];

    /**
     * 类型转换
     *
     * @var array
     */
    protected $casts = [
        'difficulty' => 'integer',
        'frequency' => 'integer',
        'example_sentences' => 'array',
    ];

    /**
     * 获取所属单词书
     */
    public function book()
    {
        return $this->belongsTo(Book::class, 'word_book_id');
    }

    /**
     * 获取此单词的所有用户学习记录
     */
    public function userWords()
    {
        return $this->hasMany(UserWord::class, 'word_id');
    }
} 