<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    protected $fillable = [
        'code', 'status'
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
