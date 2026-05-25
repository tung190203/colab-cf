<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffSchedule extends Model
{
    protected $fillable = ['staff_id', 'date', 'shift', 'status', 'is_ot', 'is_holiday', 'ot_multiplier', 'note'];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_ot' => 'boolean',
        'is_holiday' => 'boolean',
        'ot_multiplier' => 'float',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
