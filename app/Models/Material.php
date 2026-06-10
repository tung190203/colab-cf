<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Material extends Model
{
    protected $fillable = [
        'name',
        'unit',
        'current_stock',
        'low_stock_threshold',
        'price_per_unit',
        'note',
        'active',
    ];

    protected $casts = [
        'current_stock' => 'decimal:3',
        'low_stock_threshold' => 'decimal:3',
        'price_per_unit' => 'decimal:2',
        'active' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function (Material $material) {
            if ((float) $material->current_stock < 0) {
                throw ValidationException::withMessages([
                    'current_stock' => 'Tồn kho không được âm',
                ]);
            }
        });
    }
}
