<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Extra extends Model
{
    protected $fillable = [
        'sku', 'category', 'name', 'description', 'image', 'price', 'status', 'stock_tracking', 'tags', 'toppings'
    ];

    protected $casts = [
        'tags' => 'array',
        'toppings' => 'array',
        'status' => 'boolean',
        'stock_tracking' => 'boolean'
    ];

    protected static function booted()
    {
        static::created(function ($extra) {
            if (empty($extra->sku)) {
                $extra->sku = 'SP-' . str_pad($extra->id, 3, '0', STR_PAD_LEFT);
                $extra->save();
            }
        });
    }

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_extras')->withPivot('quantity');
    }
}
