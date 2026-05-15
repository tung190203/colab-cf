<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name', 'category', 'price', 'duration', 'duration_label', 'is_active', 'free_drinks_count'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'free_drinks_count' => 'integer',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
