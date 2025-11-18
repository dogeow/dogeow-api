<?php

namespace App\Models\Wiki;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WikiLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'target_id',
        'type',
    ];

    /**
     * 获取源节点
     */
    public function sourceNode()
    {
        return $this->belongsTo(WikiNode::class, 'source_id');
    }

    /**
     * 获取目标节点
     */
    public function targetNode()
    {
        return $this->belongsTo(WikiNode::class, 'target_id');
    }
}

