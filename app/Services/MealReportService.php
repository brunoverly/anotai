<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Support\Carbon;

class MealReportService
{
    public function resumoDia(int $chatId): ?array
    {
        return $this->resumo($chatId, now()->startOfDay(), now()->endOfDay(), 'dia');
    }

    public function resumoSemana(int $chatId): ?array
    {
        return $this->resumo($chatId, now()->startOfWeek(), now()->endOfWeek(), 'semana');
    }

    private function resumo(int $chatId, Carbon $inicio, Carbon $fim, string $periodo): ?array
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return null;
        }

        $meals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [$inicio, $fim])
            ->orderBy('consumed_at')
            ->get();

        $userMacroGoals = [
            'calories_kcal' => $user->calories_kcal,
            'carbohydrate_g' => $user->carbohydrate_g,
            'protein_g' => $user->protein_g,
            'fat_g' => $user->fat_g,
        ];

        $periodoFormatado = $periodo === 'dia'
            ? $inicio->format('d/m')
            : $inicio->format('d/m') . ' a ' . $fim->format('d/m');

        if(empty($userMacroGoals['calories_kcal']) || empty($userMacroGoals['carbohydrate_g']) || empty($userMacroGoals['protein_g']) || empty($userMacroGoals['fat_g'])) {
           return [
            'periodo'               => $periodo,
            'periodo_formatado'     => $periodoFormatado,
            'quantidade_refeicoes'  => $meals->count(),
            'total_protein_g'       => round($meals->sum('total_protein_g'), 2),
            'total_carbohydrate_g'  => round($meals->sum('total_carbohydrate_g'), 2),
            'total_fat_g'           => round($meals->sum('total_fat_g'), 2),
            'total_calories_kcal'   => round($meals->sum('total_calories_kcal'), 0),
        ];
        }else{
            // As metas cadastradas são diárias — na visão semanal, a meta de
            // comparação precisa ser a semana inteira (7 dias), senão qualquer
            // soma de mais de um dia pareceria "estourada" sem sentido.
            $multiplicador = $periodo === 'semana' ? 7 : 1;

            return [
            'periodo'               => $periodo,
            'periodo_formatado'     => $periodoFormatado,
            'quantidade_refeicoes'  => $meals->count(),
            'total_protein_g'       => round($meals->sum('total_protein_g'), 2),
            'user_protein_goal_g'     => $userMacroGoals['protein_g'] * $multiplicador,
            'total_carbohydrate_g'  => round($meals->sum('total_carbohydrate_g'), 2),
            'user_carbohydrate_goal_g' => $userMacroGoals['carbohydrate_g'] * $multiplicador,
            'total_fat_g'           => round($meals->sum('total_fat_g'), 2),
            'user_fat_goal_g'         => $userMacroGoals['fat_g'] * $multiplicador,
            'total_calories_kcal'   => round($meals->sum('total_calories_kcal'), 0),
            'user_calories_goal_kcal' => $userMacroGoals['calories_kcal'] * $multiplicador,
        ];
        }

    }

    public function excluirUltimaRefeicao(int $chatId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return false;
        }

        $ultimaRefeicao = Meal::where('user_id', $user->id)->whereDate('consumed_at',today())->where('deleted_at', '=', null)
            ->orderByDesc('consumed_at')
            ->first();

        if ($ultimaRefeicao) {
            $ultimaRefeicao->delete();
            return [
                'items' => $ultimaRefeicao->items,
                'total_calories_kcal' => $ultimaRefeicao->total_calories_kcal,
                'total_protein_g' => $ultimaRefeicao->total_protein_g,
                'total_carbohydrate_g' => $ultimaRefeicao->total_carbohydrate_g,
                'total_fat_g' => $ultimaRefeicao->total_fat_g,
            ];
        }

        return false;
    }

    public function saveUserMacros(int $chatId, $macros)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return false;
        }

        return $user->update([
            'calories_kcal' => $macros['calories_kcal'],
            'carbohydrate_g' => $macros['carbohydrate_g'],
            'protein_g' => $macros['protein_g'],
            'fat_g' => $macros['fat_g'],
        ]);

    }
}
