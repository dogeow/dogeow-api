<?php

namespace App\Models\Thing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_id',
    ];

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 