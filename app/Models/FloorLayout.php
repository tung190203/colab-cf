<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FloorLayout extends Model
{
    protected $fillable = [
        'floor',
        'name',
        'layout_json',
    ];

    protected $casts = [
        'layout_json' => 'array',
    ];
}
