<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    protected $table = 'foods';

    protected $fillable = [
        'name',
        'protein_g',
        'carbohydrate_g',
        'fat_g',
        'calories_kcal',
        'serving_name',
        'serving_size_g',
        'source',
    ];
}
