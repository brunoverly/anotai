<?php

use App\Http\Controllers\WhatssapController;
use Illuminate\Support\Facades\Route;

Route::get('/webhook', [WhatssapController::class, 'verify']);
Route::post('/webhook', [WhatssapController::class, 'receive']);
