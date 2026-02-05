<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserHomeLayout extends Model
{
    use HasFactory;

    protected $table = 'user_home_layouts';

    protected $fillable = [
        'user_id',
        'layout',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
