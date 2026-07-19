<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Food extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'protein_g',
        'carbohydrate_g',
        'fat_g',
        'calories_kcal',
        'source',
    ];
}
