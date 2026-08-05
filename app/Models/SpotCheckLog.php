<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotCheckLog extends Model
{
    protected $fillable = [
        'date',
        'shift_type',
        'shift_start',
        'checked_at',
        'checker_id',
        'checker_role',
        'staff_ids',
        'items_checked',
        'total_checked',
        'total_passed',
        'score',
        'is_locked',
    ];

    protected $casts = [
        'date' => 'date',
        'shift_start' => 'datetime',
        'checked_at' => 'datetime',
        'staff_ids' => 'array',
        'items_checked' => 'array',
        'total_checked' => 'integer',
        'total_passed' => 'integer',
        'score' => 'integer',
        'is_locked' => 'boolean',
    ];

    public function checker()
    {
        return $this->belongsTo(User::class, 'checker_id');
    }
}
