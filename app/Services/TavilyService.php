<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TavilyService
{
    public function search(string $query): array
    {
        $apiKey = config('services.tavily.api_key');

        if (empty($apiKey)) {
            Log::warning('Tavily API Key não configurada, busca de grounding ignorada.');
            return [];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post(rtrim(config('services.tavily.base_url'), '/') . '/search', [
                    'query' => $query,
                    'search_depth' => 'basic',
                    'max_results' => (int) config('services.tavily.max_results', 4),
                    'days' => (int) config('services.tavily.days', 7),
                ]);

            if (!$response->successful()) {
                Log::warning('Falha na busca da Tavily', ['status' => $response->status(), 'body' => $response->body()]);
                return [];
            }

            $resultados = $response->json('results') ?? [];

            return collect($resultados)
                ->map(fn ($resultado) => [
                    'titulo' => $resultado['title'] ?? '',
                    'conteudo' => \Illuminate\Support\Str::limit($resultado['content'] ?? '', 600),
                    'url' => $resultado['url'] ?? '',
                ])
                ->all();
        } catch (\Throwable $t) {
            Log::error('Erro ao consultar a Tavily: ' . $t->getMessage());
            return [];
        }
    }
}
