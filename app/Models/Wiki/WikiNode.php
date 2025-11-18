<?php

namespace App\Models\Wiki;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WikiNode extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'tags',
        'summary',
        'content',
        'content_markdown',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    /**
     * 获取从该节点出发的链接
     */
    public function linksFrom()
    {
        return $this->hasMany(WikiLink::class, 'source_id');
    }

    /**
     * 获取指向该节点的链接
     */
    public function linksTo()
    {
        return $this->hasMany(WikiLink::class, 'target_id');
    }

    /**
     * 获取所有相关的链接（作为源或目标）
     */
    public function links()
    {
        return WikiLink::where('source_id', $this->id)
            ->orWhere('target_id', $this->id)
            ->get();
    }
}

