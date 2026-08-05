<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpotCheckItem extends Model
{
    protected $fillable = [
        'group',
        'title',
        'order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
