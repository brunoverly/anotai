<?php

use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IphoneController;

Route::post('/telegram/webhook', [TelegramController::class, 'receive'])
    ->middleware('throttle:30,1');

Route::post('/iphone/webhook', [IphoneController::class, 'receive'])
    ->middleware('throttle:30,1');
