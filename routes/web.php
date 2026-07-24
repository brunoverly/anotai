<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

Route::get('/dashboard/{chatId}/login', [DashboardController::class, 'login'])->middleware('signed')->name('dashboard.login');

Route::middleware('dashboard.access')->group(function () {
    Route::get('/dashboard/{chatId}', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboard/{chatId}/week', [DashboardController::class, 'week'])->name('dashboard.week');
    Route::get('/dashboard/{chatId}/meals', [DashboardController::class, 'meals'])->name('dashboard.meals');
    Route::delete('/dashboard/{chatId}/meals/{mealId}', [DashboardController::class, 'mealsDestroy'])->name('dashboard.meals.destroy');
    Route::get('/dashboard/{chatId}/macros', [DashboardController::class, 'showMacros'])->name('dashboard.macros');
    Route::post('/dashboard/{chatId}/macros', [DashboardController::class, 'updateMacros'])->name('dashboard.macros.update');
    Route::get('/dashboard/{chatId}/day/{day}', [DashboardController::class, 'day'])->name('dashboard.day');
    Route::get('/dashboard/{chatId}/meal/macro/{mealId}', [DashboardController::class, 'showMeal'])->name('dashboard.showMeal');
    Route::post('/dashboard/{chatId}/meal/{mealId}', [DashboardController::class, 'updateMeal'])->name('dashboard.updateMeal');
});


