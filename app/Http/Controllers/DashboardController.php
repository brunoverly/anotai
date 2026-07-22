<?php

namespace App\Http\Controllers;

use App\Services\MealReportService;
use Illuminate\Http\Request;
use App\Models\Meal;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(
        int $chatId,
        MealReportService $mealReportService
    )
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $userName = $user->name;
        $resumo = $mealReportService->resumoDia($chatId);

        if (!isset($resumo['user_calories_goal_kcal'])) {
            $resumo['user_calories_goal_kcal'] = 0;
            $resumo['user_carbohydrate_goal_g'] = 0;
            $resumo['user_protein_goal_g'] = 0;
            $resumo['user_fat_goal_g'] = 0;
            $resumo['%_calories'] = "";
            $resumo['%_carbohydrate'] = "";
            $resumo['%_protein'] = "";
            $resumo['%_fat'] = "";
        } else {
            $resumo['%_calories'] = round(($resumo['total_calories_kcal'] / $resumo['user_calories_goal_kcal']) * 100) . '%';
            $resumo['%_carbohydrate'] = round(($resumo['total_carbohydrate_g'] / $resumo['user_carbohydrate_goal_g']) * 100) . '%';
            $resumo['%_protein'] = round(($resumo['total_protein_g'] / $resumo['user_protein_goal_g']) * 100) . '%';
            $resumo['%_fat'] = round(($resumo['total_fat_g'] / $resumo['user_fat_goal_g']) * 100) . '%';
        }

        $dayMeals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('deleted_at', '=', null)
            ->orderBy('consumed_at', 'desc')
            ->get(['consumed_at', 'total_protein_g', 'total_carbohydrate_g', 'total_fat_g', 'total_calories_kcal']);

        $last7DaysMeals = $mealReportService->last7days($chatId);

        return view('dashboard.resumo', [
            'userName' => $userName,
            'resumo' => $resumo,
            'last7DaysMeals' => $last7DaysMeals,
            'dayMeals' => $dayMeals,
        ]);
    }
}
