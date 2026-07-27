<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    protected $fillable = [
        'code', 'status', 'category', 'total_seating', 'is_active', 'floor', 'seat_price', 'block_price'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'floor' => 'integer',
        'seat_price' => 'integer',
        'block_price' => 'integer',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeFree($query)
    {
        return $query->where('status', 'free');
    }

    public function scopeOccupied($query)
    {
        return $query->where('status', 'occupied');
    }
}
