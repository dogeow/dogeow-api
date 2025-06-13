<?php

namespace App\Models\Thing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Room extends Model
{
    use HasFactory;

    protected $table = 'thing_rooms';

    protected $fillable = [
        'name',
        'area_id',
        'user_id',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function spots()
    {
        return $this->hasMany(Spot::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 