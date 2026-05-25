<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'staff_id', 'date', 'shift', 'check_in_at', 'check_out_at', 'note',
        'is_manual_adjusted', 'adjusted_by', 'adjusted_at',
    ];

    protected $casts = [
        'date'         => 'date:Y-m-d',
        'check_in_at'  => 'datetime',
        'check_out_at' => 'datetime',
        'is_manual_adjusted' => 'boolean',
        'adjusted_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
