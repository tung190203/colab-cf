<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Extra extends Model
{
    protected $fillable = [
        'category', 'name', 'price'
    ];

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_extras')->withPivot('quantity');
    }
}
