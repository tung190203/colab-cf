<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $fillable = [
        'package_id', 'table_id', 'start_time', 'end_time', 'total_price', 'payment_method', 'status', 'full_name', 'phone', 'proof_image', 'is_served', 'mode_booking', 'note'
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function extras()
    {
        return $this->belongsToMany(Extra::class, 'booking_extras')->withPivot('quantity');
    }

    public function getProofImageAttribute($value)
    {
        return $value ? 'storage/' . $value : null;
    }
}
