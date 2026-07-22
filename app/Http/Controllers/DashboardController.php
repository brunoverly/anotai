<?php

namespace App\Http\Controllers;

use App\Services\MealReportService;
use Illuminate\Http\Request;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Support\Carbon;

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
        $resumo = $mealReportService->resumoDia($chatId, $user);

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

        $last7DaysMeals = $mealReportService->last7days($chatId, $user);

        return view('dashboard.resumo', [
            'chatId' => $chatId,
            'userName' => $userName,
            'resumo' => $resumo,
            'last7DaysMeals' => $last7DaysMeals,
            'dayMeals' => $dayMeals,
        ]);
    }

    public function week(
        int $chatId,
        MealReportService $mealReportService
    )
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $userName = $user->name;
        $last7DaysMeals = array_reverse($mealReportService->last7days($chatId, $user), true);

        $diasSemanaCompleto = [
            0 => 'Domingo',
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        ];

        foreach ($last7DaysMeals as $diaAbrev => $dados) {
            $data = Carbon::parse($dados['data']);

            if ($data->isToday()) {
                $label = 'Hoje';
            } elseif ($data->isYesterday()) {
                $label = 'Ontem';
            } else {
                $label = $diasSemanaCompleto[$data->dayOfWeek];
            }

            $last7DaysMeals[$diaAbrev]['label'] = $label;
        }

        return view('dashboard.week', [
            'chatId' => $chatId,
            'userName' => $userName,
            'last7DaysMeals' => $last7DaysMeals,
        ]);
    }

    public function meals(int $chatId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $userName = $user->name;
        $dayMeals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->where('deleted_at', '=', null)
            ->orderBy('consumed_at', 'desc')
            ->get(['id', 'consumed_at','total_calories_kcal', 'total_protein_g', 'total_carbohydrate_g', 'total_fat_g', 'items']);


        return view('dashboard.meals', [
            'chatId' => $chatId,
            'userName' => $userName,
            'dayMeals' => $dayMeals,
        ]);
    }

    public function mealsDestroy(int $chatId, int $mealId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $meal = Meal::where('id', $mealId)
            ->where('user_id', $user->id)
            ->where('deleted_at', '=', null)
            ->first();

        if (!$meal) {
            return response()->json(['error' => 'Meal not found'], 404);
        }

        $meal->delete();

        return response()->json(['success' => true]);

    }

    public function showMacros(int $chatId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first(['id', 'name', 'calories_kcal', 'carbohydrate_g', 'protein_g', 'fat_g']);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return view('dashboard.macros', [
            'chatId' => $chatId,
            'user' => $user,
        ]);
    }

    public function updateMacros(Request $request, int $chatId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $validatedData = $request->validate([
            'calories_kcal' => 'required|numeric|min:0',
            'carbohydrate_g' => 'required|numeric|min:0',
            'protein_g' => 'required|numeric|min:0',
            'fat_g' => 'required|numeric|min:0',
        ]);

        $user->update($validatedData);

        return redirect()->route('dashboard.macros', $chatId)->with('success', 'Metas atualizadas com sucesso!');

    }

    public function day(
        int $chatId,
        String $day,
        MealReportService $mealReportService
    )
    {
        $day = Carbon::parse($day);
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $userName = $user->name;
        $resumo = $mealReportService->resumoData($chatId, $day, $user);

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

        $dayStart = $day->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();

        $dayMeals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [$dayStart, $dayEnd])
            ->where('deleted_at', '=', null)
            ->orderBy('consumed_at', 'desc')
            ->get(['consumed_at', 'total_protein_g', 'total_carbohydrate_g', 'total_fat_g', 'total_calories_kcal']);


        return view('dashboard.resumo', [
            'chatId' => $chatId,
            'userName' => $userName,
            'resumo' => $resumo,
            'dayMeals' => $dayMeals,
        ]);
    }

    public function showMeal(int $chatId, int $mealId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $meal = Meal::where('id', $mealId)
            ->where('user_id', $user->id)
            ->where('deleted_at', '=', null)
            ->first();

        if (!$meal) {
            return response()->json(['error' => 'Meal not found'], 404);
        }

        return view('dashboard.macros', [
            'chatId' => $chatId,
            'meal' => $meal,
        ]);
    }

    public function updateMeal(
        Request $request,
        int $chatId,
        int $mealId
    ){
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $meal = Meal::where('id', $mealId)
            ->where('user_id', $user->id)
            ->where('deleted_at', '=', null)
            ->first();

        if (!$meal) {
            return response()->json(['error' => 'Meal not found'], 404);
        }

        $validatedData = $request->validate([
            'total_calories_kcal' => 'required|numeric|min:0',
            'total_protein_g' => 'required|numeric|min:0',
            'total_carbohydrate_g' => 'required|numeric|min:0',
            'total_fat_g' => 'required|numeric|min:0',
        ]);

        $meal->update($validatedData);

        return redirect()->route('dashboard.showMeal', ['chatId' => $chatId, 'mealId' => $mealId])->with('success', 'Refeição atualizada com sucesso!');

    }
}
