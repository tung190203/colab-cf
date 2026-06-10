<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosOrder extends Model
{
    protected $fillable = [
        'order_code',
        'payment_method',
        'total_quantity',
        'total_amount',
        'note',
        'created_by',
    ];

    protected $casts = [
        'total_quantity' => 'integer',
        'total_amount' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(PosOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
