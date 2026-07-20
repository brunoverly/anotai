<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meal extends Model
{
    use SoftDeletes;

    protected $table = 'meals';

    protected $fillable = [
        'user_id',
        'telegram_update_id',
        'raw_text',
        'items',
        'total_protein_g',
        'total_carbohydrate_g',
        'total_fat_g',
        'total_calories_kcal',
        'consumed_at',
    ];

    protected $casts = [
        'items' => 'array',
        'consumed_at' => 'datetime',
        'total_protein_g' => 'float',
        'total_carbohydrate_g' => 'float',
        'total_fat_g' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
