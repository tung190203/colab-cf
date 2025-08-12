<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name', 'price', 'duration', 'duration_label'
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
