<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GymCheckIn extends Model
{
    use HasFactory;

    protected $table = 'gym_check_ins';

    protected $fillable = [
        'check_in_date',
        'checked_in_at',
        'tipo',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'checked_in_at' => 'datetime',
    ];
}
