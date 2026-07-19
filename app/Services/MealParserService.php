<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MealParserService
{
    public function parseMealText(string $text)
    {
        $apiKey = config('services.groq.api_key');

        $response = Http::withToken($apiKey)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-8b-instant',
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um analisador de refeições. Extraia os alimentos e quantidades do texto. Responda APENAS um JSON no formato: {"alimentos": [{"alimento": "frango", "quantidade": 50, "unidade": "g"}]}'
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ]
                ],
                'temperature' => 0.1,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            return json_decode($data['choices'][0]['message']['content'], true);
        }

        Log::error('Falha ao sanitizar refeição com a Groq', ['body' => $response->body()]);
        return false;
    }
}
