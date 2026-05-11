<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    protected $fillable = [
        'staff_id', 'month', 'year',
        'hourly_rate', 'worked_hours', 'calculated_salary', 'bonus', 'deduction', 'total', 'note',
        'bonus_details', 'deduction_details', 'is_settled'
    ];

    protected $casts = [
        'bonus_details' => 'array',
        'deduction_details' => 'array',
        'is_settled' => 'boolean',
    ];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
