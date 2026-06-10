<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recipe extends Model
{
    protected $fillable = [
        'product_id',
        'product_name',
        'ingredients',
        'active',
    ];

    protected $casts = [
        'ingredients' => 'array',
        'active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Extra::class, 'product_id');
    }
}
