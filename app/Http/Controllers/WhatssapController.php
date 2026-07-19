<?php

namespace App\Http\Controllers;

use App\Models\TelegramMessage;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function receive(Request $request)
    {
        $payload = $request->all();

        TelegramMessage::create([

        ]);

        return response('Received', 200);
    }
}
