<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;

class WhatssapController extends Controller
{
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === env('WHATSAPP_VERIFY_TOKEN')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request)
    {
        $payload = $request->all();

        WhatsAppMessage::create([
            'from' => $payload['entry'][0]['changes'][0]['value']['messages'][0]['from'],
            'message_id' => $payload['entry'][0]['changes'][0]['value']['messages'][0]['id'],
            'type' => $payload['entry'][0]['changes'][0]['value']['messages'][0]['type'],
            'payload' => $payload
        ]);

        return response('Received', 200);
    }
}
