<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Support\Carbon;

class MealReportService
{
    public function resumoDia(int $chatId, ?User $user = null): ?array
    {
        return $this->resumo($chatId, now()->startOfDay(), now()->endOfDay(), 'dia', $user);
    }

    public function resumoSemana(int $chatId, ?User $user = null): ?array
    {
        return $this->resumo($chatId, now()->startOfWeek(), now()->endOfWeek(), 'semana', $user);
    }


    private function resumo(int $chatId, Carbon $inicio, Carbon $fim, string $periodo, ?User $user = null): ?array
    {
        // $user pode ser passado pelo chamador (ex: DashboardController, que já
        // carregou o User antes) pra evitar buscar o mesmo usuário de novo no
        // banco a cada método chamado — cada busca é uma ida e volta pro Postgres
        // remoto (Supabase), então repetir isso em toda chamada custa caro.
        $user = $user ?? User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return null;
        }

        $meals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [$inicio, $fim])
            ->where('deleted_at', '=', null)
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
            'total_fat_g'           => round($meals->sum('total_fat_g'), 2),
            'total_carbohydrate_g'  => round($meals->sum('total_carbohydrate_g'), 2),
            'total_calories_kcal'   => round($meals->sum('total_calories_kcal'), 0),
            'user_protein_goal_g'     => $userMacroGoals['protein_g'] * $multiplicador,
            'user_carbohydrate_goal_g' => $userMacroGoals['carbohydrate_g'] * $multiplicador,
            'user_fat_goal_g'         => $userMacroGoals['fat_g'] * $multiplicador,
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


    public function last7days($chatId, ?User $user = null): ?array
    {
        $user = $user ?? User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return null;
        }

        // "Últimos 7 dias" não inclui hoje — vai de ontem pra trás.
        $inicio = now()->subDays(7)->startOfDay();
        $fim = now()->subDay()->endOfDay();

        $meals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [$inicio, $fim])
            ->where('deleted_at', '=', null)
            ->orderBy('consumed_at', 'desc')
            ->get();

        $userMacroGoals = [
            'calories_kcal' => $user->calories_kcal,
            'carbohydrate_g' => $user->carbohydrate_g,
            'protein_g' => $user->protein_g,
            'fat_g' => $user->fat_g,
        ];
        $temMeta = !empty($userMacroGoals['calories_kcal'])
            && !empty($userMacroGoals['carbohydrate_g'])
            && !empty($userMacroGoals['protein_g'])
            && !empty($userMacroGoals['fat_g']);

        $daysOfTheWeek = [
            0 => 'Dom',
            1 => 'Seg',
            2 => 'Ter',
            3 => 'Qua',
            4 => 'Qui',
            5 => 'Sex',
            6 => 'Sáb',
        ];

        $last7Days = [];

        for ($dia = $inicio->copy(); $dia->lte($fim); $dia->addDay()) {
            $refeicoesDoDia = $meals->filter(fn ($meal) => $meal->consumed_at->isSameDay($dia));

            $diaResumo = [
                'data' => $dia->toDateString(),
                'total_calories_kcal' => round($refeicoesDoDia->sum('total_calories_kcal'), 0),
                'total_protein_g' => round($refeicoesDoDia->sum('total_protein_g'), 2),
                'total_carbohydrate_g' => round($refeicoesDoDia->sum('total_carbohydrate_g'), 2),
                'total_fat_g' => round($refeicoesDoDia->sum('total_fat_g'), 2),
            ];

            if (!$temMeta) {
                $diaResumo['user_calories_goal_kcal'] = 0;
                $diaResumo['user_carbohydrate_goal_g'] = 0;
                $diaResumo['user_protein_goal_g'] = 0;
                $diaResumo['user_fat_goal_g'] = 0;
                $diaResumo['%_calories'] = "";
                $diaResumo['%_carbohydrate'] = "";
                $diaResumo['%_protein'] = "";
                $diaResumo['%_fat'] = "";
            } else {
                $diaResumo['user_calories_goal_kcal'] = $userMacroGoals['calories_kcal'];
                $diaResumo['user_carbohydrate_goal_g'] = $userMacroGoals['carbohydrate_g'];
                $diaResumo['user_protein_goal_g'] = $userMacroGoals['protein_g'];
                $diaResumo['user_fat_goal_g'] = $userMacroGoals['fat_g'];
                $diaResumo['%_calories'] = round(($diaResumo['total_calories_kcal'] / $userMacroGoals['calories_kcal']) * 100) . '%';
                $diaResumo['%_carbohydrate'] = round(($diaResumo['total_carbohydrate_g'] / $userMacroGoals['carbohydrate_g']) * 100) . '%';
                $diaResumo['%_protein'] = round(($diaResumo['total_protein_g'] / $userMacroGoals['protein_g']) * 100) . '%';
                $diaResumo['%_fat'] = round(($diaResumo['total_fat_g'] / $userMacroGoals['fat_g']) * 100) . '%';
            }

            $last7Days[$daysOfTheWeek[$dia->dayOfWeek]] = $diaResumo;
        }

        return $last7Days;
    }

    public function resumoData(int $chatId, Carbon $data, ?User $user = null): ?array
    {
        return $this->resumo($chatId, $data->copy()->startOfDay(), $data->copy()->endOfDay(), 'dia', $user);
    }

}
