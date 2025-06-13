<?php

namespace App\Models\Thing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Spot extends Model
{
    use HasFactory;

    protected $table = 'thing_spots';

    protected $fillable = [
        'name',
        'room_id',
        'user_id',
    ];

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 