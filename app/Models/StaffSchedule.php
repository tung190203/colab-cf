<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffSchedule extends Model
{
    protected $fillable = ['staff_id', 'date', 'shift', 'status', 'note'];

    protected $casts = ['date' => 'date:Y-m-d'];

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
