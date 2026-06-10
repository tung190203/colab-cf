<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeLog extends Model
{
    protected $fillable = [
        'recipe_id',
        'product_id',
        'product_name',
        'ingredients_before',
        'ingredients_after',
        'changed_by',
    ];

    protected $casts = [
        'ingredients_before' => 'array',
        'ingredients_after' => 'array',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
