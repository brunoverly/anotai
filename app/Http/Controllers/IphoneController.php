<?php

namespace App\Http\Controllers;

use App\Models\GymCheckIn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IphoneController extends Controller
{
    public function receive(Request $request)
    {
        $expectedToken = config('services.gymtrack.token');
        $providedToken = (string) $request->header('X-GymTrack-Token', '');

        if (empty($expectedToken) || !hash_equals($expectedToken, $providedToken)) {
            Log::warning('Requisição do GymTrack recusada: token ausente, inválido, ou não configurado no servidor');

            return response()->json(['status' => 'forbidden'], 403);
        }

        $now = now();

        $checkIn = GymCheckIn::firstOrCreate(
            ['check_in_date' => $now->toDateString()],
            ['checked_in_at' => $now],
        );

        Log::info('Check-in da academia registrado', [
            'check_in_date' => $checkIn->check_in_date->toDateString(),
            'checked_in_at' => $checkIn->checked_in_at,
            'novo_registro' => $checkIn->wasRecentlyCreated,
        ]);

        return response()->json(['status' => 'success']);
    }
}
