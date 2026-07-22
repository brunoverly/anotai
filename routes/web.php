<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard/{chatId}', [DashboardController::class, 'index']);
Route::get('/dashboard/{chatId}/week', [DashboardController::class, 'week']);
Route::get('/dashboard/{chatId}/meals', [DashboardController::class, 'meals']);
Route::delete('/dashboard/{chatId}/meals/{mealId}', [DashboardController::class, 'mealsDestroy']);
Route::get('/dashboard/{chatId}/macros', [DashboardController::class, 'showMacros'])->name('dashboard.macros');
Route::post('/dashboard/{chatId}/macros', [DashboardController::class, 'updateMacros']);
Route::get('/dashboard/{chatId}/day/{day}', [DashboardController::class, 'day'])->name('dashboard.day');
Route::get('/dashboard/{chatId}/meal/macro/{mealId}', [DashboardController::class, 'showMeal'])->name('dashboard.showMeal');
Route::post('/dashboard/{chatId}/meal/{mealId}', [DashboardController::class, 'updateMeal'])->name('dashboard.updateMeal');



