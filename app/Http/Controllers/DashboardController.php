<?php

namespace App\Http\Controllers;

use App\Services\MealService;
use Illuminate\Http\Request;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index(
        int $chatId,
        MealService $mealReportService
    )
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $userName = $user->name;
        $summary = $mealReportService->summaryDay($chatId, $user);

        if (!isset($summary['user_calories_goal_kcal'])) {
            $summary['user_calories_goal_kcal'] = 0;
            $summary['user_carbohydrate_goal_g'] = 0;
            $summary['user_protein_goal_g'] = 0;
            $summary['user_fat_goal_g'] = 0;
            $summary['%_calories'] = "";
            $summary['%_carbohydrate'] = "";
            $summary['%_protein'] = "";
            $summary['%_fat'] = "";
        } else {
            $summary['%_calories'] = round(($summary['total_calories_kcal'] / $summary['user_calories_goal_kcal']) * 100) . '%';
            $summary['%_carbohydrate'] = round(($summary['total_carbohydrate_g'] / $summary['user_carbohydrate_goal_g']) * 100) . '%';
            $summary['%_protein'] = round(($summary['total_protein_g'] / $summary['user_protein_goal_g']) * 100) . '%';
            $summary['%_fat'] = round(($summary['total_fat_g'] / $summary['user_fat_goal_g']) * 100) . '%';
        }

        $dayMeals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('deleted_at', '=', null)
            ->orderBy('consumed_at', 'desc')
            ->get(['consumed_at', 'total_protein_g', 'total_carbohydrate_g', 'total_fat_g', 'total_calories_kcal']);

        $last7DaysMeals = $mealReportService->last7days($chatId, $user);

        $chartLabels = collect($last7DaysMeals)->map(function ($data, $weekDay) {
            return Carbon::parse($data['data'])->isYesterday() ? 'Ontem' : $weekDay;
        })->values()->all();

        $streak = $mealReportService->currentStreak($chatId, $user);

        return view('dashboard.summary', [
            'chatId' => $chatId,
            'userName' => $userName,
            'summary' => $summary,
            'last7DaysMeals' => $last7DaysMeals,
            'chartLabels' => $chartLabels,
            'streak' => $streak,
            'dayMeals' => $dayMeals,
            'current' => 'summary',
        ]);
    }

    public function week(
        int $chatId,
        MealService $mealReportService
    )
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $userName = $user->name;
        $last7DaysMeals = array_reverse($mealReportService->last7days($chatId, $user), true);

        $weekDaysFullName = [
            0 => 'Domingo',
            1 => 'Segunda-feira',
            2 => 'Terça-feira',
            3 => 'Quarta-feira',
            4 => 'Quinta-feira',
            5 => 'Sexta-feira',
            6 => 'Sábado',
        ];

        foreach ($last7DaysMeals as $weekDays => $data) {
            $data = Carbon::parse($data['data']);

            if ($data->isToday()) {
                $label = 'Hoje';
            } elseif ($data->isYesterday()) {
                $label = 'Ontem';
            } else {
                $label = $weekDaysFullName[$data->dayOfWeek];
            }

            $last7DaysMeals[$weekDays]['label'] = $label;
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
            Log::warning('Tentativa de excluir refeição inexistente pelo dashboard', ['chat_id' => $chatId, 'meal_id' => $mealId]);
            return response()->json(['error' => 'Meal not found'], 404);
        }

        $meal->delete();

        Log::info('Refeição excluída pelo dashboard', ['chat_id' => $chatId, 'meal_id' => $mealId]);

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

        Log::info('Metas de macros atualizadas pelo dashboard', ['chat_id' => $chatId, 'macros' => $validatedData]);

        return redirect()->route('dashboard.macros', $chatId)->with('success', 'Metas atualizadas com sucesso!');

    }

    public function day(
        int $chatId,
        String $day,
        MealService $mealReportService
    )
    {
        $day = Carbon::parse($day);
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $userName = $user->name;
        $summary = $mealReportService->summaryData($chatId, $day, $user);

        if (!isset($summary['user_calories_goal_kcal'])) {
            $summary['user_calories_goal_kcal'] = 0;
            $summary['user_carbohydrate_goal_g'] = 0;
            $summary['user_protein_goal_g'] = 0;
            $summary['user_fat_goal_g'] = 0;
            $summary['%_calories'] = "";
            $summary['%_carbohydrate'] = "";
            $summary['%_protein'] = "";
            $summary['%_fat'] = "";
        } else {
            $summary['%_calories'] = round(($summary['total_calories_kcal'] / $summary['user_calories_goal_kcal']) * 100) . '%';
            $summary['%_carbohydrate'] = round(($summary['total_carbohydrate_g'] / $summary['user_carbohydrate_goal_g']) * 100) . '%';
            $summary['%_protein'] = round(($summary['total_protein_g'] / $summary['user_protein_goal_g']) * 100) . '%';
            $summary['%_fat'] = round(($summary['total_fat_g'] / $summary['user_fat_goal_g']) * 100) . '%';
        }

        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $dayMeals = Meal::where('user_id', $user->id)
            ->whereBetween('consumed_at', [$start, $end])
            ->where('deleted_at', '=', null)
            ->orderBy('consumed_at', 'desc')
            ->get(['consumed_at', 'total_protein_g', 'total_carbohydrate_g', 'total_fat_g', 'total_calories_kcal']);


        return view('dashboard.summary', [
            'chatId' => $chatId,
            'userName' => $userName,
            'summary' => $summary,
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

        Log::info('Refeição editada manualmente pelo dashboard', ['chat_id' => $chatId, 'meal_id' => $mealId]);

        return redirect()->route('dashboard.showMeal', ['chatId' => $chatId, 'mealId' => $mealId])->with('success', 'Refeição atualizada com sucesso!');

    }

    public function login(int $chatId)
    {
        $user = User::where('telegram_chat_id', $chatId)->first();
        if (!$user) {
            Log::warning('Tentativa de login no dashboard com chatId inválido', ['chat_id' => $chatId]);
            return response()->json(['error' => 'User not found'], 404);
        }

        session(['chatId' => $chatId]);

        return redirect()->route('dashboard.index', ['chatId' => $chatId])->with('success', 'Login realizado com sucesso!');
    }
}
