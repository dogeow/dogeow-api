<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Note extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'notes';

    /**
     * 可批量赋值的属性
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'note_category_id',
        'title',
        'content',
        'content_markdown',
        'is_draft',
    ];

    /**
     * 应该被调整为日期的属性
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * 获取笔记所属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取笔记所属分类
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(NoteCategory::class, 'note_category_id');
    }

    /**
     * 获取笔记的标签
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(NoteTag::class, 'note_note_tag', 'note_id', 'note_tag_id')
            ->withTimestamps();
    }
}
