<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MealService
{
    public function save(array $nutritionResult, int $chatId, ?string $username, ?int $telegramUpdateId, ?string $rawText): Meal
    {
        $user = User::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'name' => $username ?? "telegram_{$chatId}",
                'telegram_username' => $username,
            ]
        );

        $data = [
            'raw_text' => $rawText,
            'items' => $nutritionResult['items'],
            'total_protein_g' => $nutritionResult['total_protein_g'],
            'total_carbohydrate_g' => $nutritionResult['total_carbohydrate_g'],
            'total_fat_g' => $nutritionResult['total_fat_g'],
            'total_calories_kcal' => $nutritionResult['total_calories_kcal'],
            'consumed_at' => now(),
        ];

        if ($telegramUpdateId === null) {
            return Meal::create($data + ['user_id' => $user->id, 'telegram_update_id' => null]);
        }

        return Meal::updateOrCreate(
            ['user_id' => $user->id, 'telegram_update_id' => $telegramUpdateId],
            $data
        );
    }

    public function summaryDay(int $chatId, ?User $user = null): ?array
    {
        return $this->summary($chatId, now()->startOfDay(), now()->endOfDay(), 'day', $user);
    }

    public function summaryWeek(int $chatId, ?User $user = null): ?array
    {
        return $this->summary($chatId, now()->startOfWeek(), now()->endOfWeek(), 'semana', $user);
    }


    private function summary(int $chatId, Carbon $start, Carbon $end, string $period, ?User $user = null): ?array
    {
        $user = $user ?? User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return null;
        }

        $meals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [$start, $end])
            ->where('deleted_at', '=', null)
            ->orderBy('consumed_at')
            ->get();

        $userMacroGoals = [
            'calories_kcal' => $user->calories_kcal,
            'carbohydrate_g' => $user->carbohydrate_g,
            'protein_g' => $user->protein_g,
            'fat_g' => $user->fat_g,
        ];

        $formatedPeriod = $period === 'day'
            ? $start->format('d/m')
            : $start->format('d/m') . ' a ' . $end->format('d/m');

        if(empty($userMacroGoals['calories_kcal']) || empty($userMacroGoals['carbohydrate_g']) || empty($userMacroGoals['protein_g']) || empty($userMacroGoals['fat_g'])) {
           return [
            'period'               => $period,
            'period_formatado'     => $formatedPeriod,
            'quantidade_refeicoes'  => $meals->count(),
            'total_protein_g'       => round($meals->sum('total_protein_g'), 2),
            'total_carbohydrate_g'  => round($meals->sum('total_carbohydrate_g'), 2),
            'total_fat_g'           => round($meals->sum('total_fat_g'), 2),
            'total_calories_kcal'   => round($meals->sum('total_calories_kcal'), 0),
        ];
        }else{
            $multiplier = $period === 'semana' ? 7 : 1;

            return [
            'period'               => $period,
            'period_formatado'     => $formatedPeriod,
            'quantidade_refeicoes'  => $meals->count(),
            'total_protein_g'       => round($meals->sum('total_protein_g'), 2),
            'total_fat_g'           => round($meals->sum('total_fat_g'), 2),
            'total_carbohydrate_g'  => round($meals->sum('total_carbohydrate_g'), 2),
            'total_calories_kcal'   => round($meals->sum('total_calories_kcal'), 0),
            'user_protein_goal_g'     => $userMacroGoals['protein_g'] * $multiplier,
            'user_carbohydrate_goal_g' => $userMacroGoals['carbohydrate_g'] * $multiplier,
            'user_fat_goal_g'         => $userMacroGoals['fat_g'] * $multiplier,
            'user_calories_goal_kcal' => $userMacroGoals['calories_kcal'] * $multiplier,
        ];
        }

    }

    public function excluirlastMeal(int $chatId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return false;
        }

        $lastMeal = Meal::where('user_id', $user->id)->whereDate('consumed_at',today())->where('deleted_at', '=', null)
            ->orderByDesc('consumed_at')
            ->first();

        if ($lastMeal) {
            $lastMeal->delete();

            Log::info('Refeição excluída pelo usuário', ['meal_id' => $lastMeal->id, 'chat_id' => $chatId]);

            return [
                'items' => $lastMeal->items,
                'total_calories_kcal' => $lastMeal->total_calories_kcal,
                'total_protein_g' => $lastMeal->total_protein_g,
                'total_carbohydrate_g' => $lastMeal->total_carbohydrate_g,
                'total_fat_g' => $lastMeal->total_fat_g,
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

        $start = now()->subDays(7)->startOfDay();
        $end = now()->subDay()->endOfDay();

        $meals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [$start, $end])
            ->where('deleted_at', '=', null)
            ->orderBy('consumed_at', 'desc')
            ->get();

        $userMacroGoals = [
            'calories_kcal' => $user->calories_kcal,
            'carbohydrate_g' => $user->carbohydrate_g,
            'protein_g' => $user->protein_g,
            'fat_g' => $user->fat_g,
        ];
        $hasGoal = !empty($userMacroGoals['calories_kcal'])
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

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $mealsOfTheDay = $meals->filter(fn ($meal) => $meal->consumed_at->isSameDay($day));

            $summaryDay = [
                'data' => $day->toDateString(),
                'total_calories_kcal' => round($mealsOfTheDay->sum('total_calories_kcal'), 0),
                'total_protein_g' => round($mealsOfTheDay->sum('total_protein_g'), 2),
                'total_carbohydrate_g' => round($mealsOfTheDay->sum('total_carbohydrate_g'), 2),
                'total_fat_g' => round($mealsOfTheDay->sum('total_fat_g'), 2),
            ];

            if (!$hasGoal) {
                $summaryDay['user_calories_goal_kcal'] = 0;
                $summaryDay['user_carbohydrate_goal_g'] = 0;
                $summaryDay['user_protein_goal_g'] = 0;
                $summaryDay['user_fat_goal_g'] = 0;
                $summaryDay['%_calories'] = "";
                $summaryDay['%_carbohydrate'] = "";
                $summaryDay['%_protein'] = "";
                $summaryDay['%_fat'] = "";
            } else {
                $summaryDay['user_calories_goal_kcal'] = $userMacroGoals['calories_kcal'];
                $summaryDay['user_carbohydrate_goal_g'] = $userMacroGoals['carbohydrate_g'];
                $summaryDay['user_protein_goal_g'] = $userMacroGoals['protein_g'];
                $summaryDay['user_fat_goal_g'] = $userMacroGoals['fat_g'];
                $summaryDay['%_calories'] = round(($summaryDay['total_calories_kcal'] / $userMacroGoals['calories_kcal']) * 100) . '%';
                $summaryDay['%_carbohydrate'] = round(($summaryDay['total_carbohydrate_g'] / $userMacroGoals['carbohydrate_g']) * 100) . '%';
                $summaryDay['%_protein'] = round(($summaryDay['total_protein_g'] / $userMacroGoals['protein_g']) * 100) . '%';
                $summaryDay['%_fat'] = round(($summaryDay['total_fat_g'] / $userMacroGoals['fat_g']) * 100) . '%';
            }

            $last7Days[$daysOfTheWeek[$day->dayOfWeek]] = $summaryDay;
        }

        return $last7Days;
    }

    public function summaryData(int $chatId, Carbon $data, ?User $user = null): ?array
    {
        return $this->summary($chatId, $data->copy()->startOfDay(), $data->copy()->endOfDay(), 'day', $user);
    }

}
