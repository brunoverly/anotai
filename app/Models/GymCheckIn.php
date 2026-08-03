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

    /**
     * Conta os dias consecutivos (terminando hoje ou ontem, sem quebrar a
     * sequência por causa do dia atual ainda estar em andamento) em que
     * houve pelo menos um check-in, de qualquer tipo.
     */
    public static function currentStreak(int $maxDays = 365): int
    {
        $hoje = now()->startOfDay();
        $inicioIntervalo = $hoje->copy()->subDays($maxDays);

        $diasComAtividade = self::whereBetween('check_in_date', [$inicioIntervalo->toDateString(), $hoje->toDateString()])
            ->pluck('check_in_date')
            ->map(fn ($data) => $data->toDateString())
            ->flip();

        $streak = 0;
        $dia = $hoje->copy();

        if (isset($diasComAtividade[$dia->toDateString()])) {
            $streak++;
        }
        $dia->subDay();

        while ($dia->gte($inicioIntervalo) && isset($diasComAtividade[$dia->toDateString()])) {
            $streak++;
            $dia->subDay();
        }

        return $streak;
    }
}
