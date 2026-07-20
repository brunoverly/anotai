<?php

namespace App\Services;

use App\Models\Food;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class NutritionManagerService
{
    /**
     * O Controller chama este cara passando o ARRAY de itens
     */
    public function processarRefeicao(array $items)
    {
        $totalProteinas = 0;
        $totalCarbos = 0;
        $totalGorduras = 0;
        $totalCalorias = 0;
        $itemsProcessados = [];

        foreach ($items as $item) {
            $nomeAlimento = $item['alimento'] ?? '';
            $quantidadeInput = (float) ($item['quantidade'] ?? 0);
            $unidadeInput = $item['unidade'] ?? 'grama';

            // Chama a escada de busca para encontrar o alimento isolado
            $food = $this->searchFood($nomeAlimento);

            if ($food) {
                // Regra de três com base nos dados encontrados
                $pesoTotalGrams = $quantidadeInput;

                // Se o input for em fatias/unidades e o banco/api tiver um tamanho de porção padrão
                if (!in_array($unidadeInput, ['grama', 'ml']) && (float)$food['serving_size_g'] > 0) {
                    $pesoTotalGrams = $quantidadeInput * (float)$food['serving_size_g'];
                }

                // Fator de multiplicação (Geralmente baseado em 100g vindo das APIs/LLM)
                $fator = $pesoTotalGrams / 100;

                // Se o alimento local foi cadastrado usando peso da porção como base em vez de 100g:
                if ($food['source'] === 'local' && (float)$food['serving_size_g'] > 1) {
                    $fator = $pesoTotalGrams / (float)$food['serving_size_g'];
                }

                $prot = round($food['protein_g'] * $fator, 2);
                $carb = round($food['carbohydrate_g'] * $fator, 2);
                $gord = round($food['fat_g'] * $fator, 2);
                $kcal = round($food['calories_kcal'] * $fator, 0);

                $totalProteinas += $prot;
                $totalCarbos += $carb;
                $totalGorduras += $gord;
                $totalCalorias += $kcal;

                $itemsProcessados[] = [
                    'alimento' => $food['name'],
                    'quantidade' => $quantidadeInput,
                    'unidade' => $unidadeInput,
                    'protein_g' => $prot,
                    'carbohydrate_g' => $carb,
                    'fat_g' => $gord,
                    'calories_kcal' => $kcal
                ];
            } else {
                // Alimento totalmente não encontrado em nenhum degrau
                $itemsProcessados[] = [
                    'alimento' => $nomeAlimento,
                    'quantidade' => $quantidadeInput,
                    'unidade' => $unidadeInput,
                    'protein_g' => 0,
                    'carbohydrate_g' => 0,
                    'fat_g' => 0,
                    'calories_kcal' => 0
                ];
            }
        }

        return [
            'items' => $itemsProcessados,
            'total_protein_g' => round($totalProteinas, 2),
            'total_carbohydrate_g' => round($totalCarbos, 2),
            'total_fat_g' => round($totalGorduras, 2),
            'total_calories_kcal' => $totalCalorias
        ];
    }

    /**
     * A ESCADA DE BUSCA ISOLADA (Retorna um Array com os dados do alimento)
     */
    private function searchFood(string $nomeAlimento)
    {
        $chaveCache = 'food:' . Str::slug($nomeAlimento);

        // ── DEGRAU 1: REDIS CACHE ──
        $cachedFood = Redis::get($chaveCache);
        if ($cachedFood) {
            Log::info("Degrau 1: Alimento encontrado no Redis -> {$nomeAlimento}");
            return json_decode($cachedFood, true);
        }

        // ── DEGRAU 2: MYSQL (BANCO LOCAL) ──
        $localFood = $this->searchInLocalDatabase($nomeAlimento);
        if ($localFood) {
            Log::info("Degrau 2: Alimento encontrado no MySQL -> {$localFood->name}");

            $foodArray = $localFood->toArray();
            $foodArray['source'] = 'local'; // Tag para identificação no cálculo

            Redis::setex($chaveCache, 86400, json_encode($foodArray));
            return $foodArray;
        }

        // ── DEGRAU 3: API EXTERNA (Open Food Facts) ──
        Log::info("Degrau 3: Buscando na API Externa -> {$nomeAlimento}");
        $apiFood = $this->searchInExternalApi($nomeAlimento);
        if ($apiFood) {
            $newFood = Food::updateOrCreate(['name' => $apiFood['name']], $apiFood);

            Redis::setex($chaveCache, 86400, json_encode($newFood));
            return $newFood->toArray();
        }

        // ── DEGRAU 4: ESTIMATIVA POR LLM (Groq) ──
        Log::info("Degrau 4: API falhou. Solicitando estimativa da LLM -> {$nomeAlimento}");
        $llmFood = $this->estimateWithLLM($nomeAlimento);
        if ($llmFood) {
            $newFood = Food::updateOrCreate(['name' => $llmFood['name']], $llmFood);

            Redis::setex($chaveCache, 86400, json_encode($newFood));
            return $newFood->toArray();
        }

        return null;
    }

    private function searchInLocalDatabase(string $nomeAlimento)
    {
        Log::info("Buscando alimento no banco local -> {$nomeAlimento}");

        $exact = Food::where('name', $nomeAlimento)->first();
        if ($exact) {
            return $exact;
        }

        // Sem match exato: prioriza o nome mais curto (mais próximo do termo buscado)
        return Food::where('name', 'like', '%' . $nomeAlimento . '%')
            ->orderByRaw('LENGTH(name) ASC')
            ->first();
    }

    private function searchInExternalApi(string $nomeAlimento)
    {
        Log::info("Log 1");
        try {
            Log::info("Log 2");
            $response = Http::timeout(30)
                ->withoutVerifying() // 👈 Adicione isso aqui para testar
                ->get("https://br.openfoodfacts.org/cgi/search.pl", [
                    'search_terms'  => $nomeAlimento,
                    'search_simple' => 1,
                    'action'        => 'process',
                    'json'          => 1,
                    'page_size'     => 1
                ]);
            Log::info("Log 3");
            Log::info("Resposta da API Externa", ['status' => $response->status(), 'body' => $response->body()]);

            if ($response->successful() && isset($response->json()['products'][0])) {
                Log::info("Log 4");
                Log::info("Alimento encontrado na API Externa -> {$nomeAlimento}");

                $product = $response->json()['products'][0];
                $nutriments = $product['nutriments'] ?? [];

                Log::info("Alimento encontrado na API Externa -> {$product['product_name']}", ['nutriments' => $nutriments]);
                // Open Food Facts usa chaves no PLURAL ('proteins_100g', 'carbohydrates_100g')
                $proteina = $nutriments['proteins_100g'] ?? $nutriments['protein_100g'] ?? null;
                $carbo    = $nutriments['carbohydrates_100g'] ?? $nutriments['carbohydrate_100g'] ?? null;
                $gordura  = $nutriments['fat_100g'] ?? $nutriments['fats_100g'] ?? 0;
                $caloria  = $nutriments['energy-kcal_100g'] ?? $nutriments['energy_kcal_100g'] ?? null;
                $caloria_backup = round(($proteina * 4) + ($carbo * 4) + ($gordura * 9), 0);

                // Se não achou caloria direta em kcal, calcula a partir dos KJs se existirem
                if (($caloria === null && isset($nutriments['energy_100g'])) or ($caloria < $proteina + $carbo + $gordura)) {
                    $caloria = $caloria_backup;
                }

                // Validação de segurança obrigatória
                if ($proteina !== null && $carbo !== null) {
                    return [
                        'name'            => strtolower($product['product_name'] ?? $nomeAlimento),
                        'protein_g'       => round((float)$proteina, 2),
                        'carbohydrate_g'  => round((float)$carbo, 2),
                        'fat_g'           => round((float)$gordura, 2),
                        'calories_kcal'   => round((float)($caloria ?? 0), 0),
                        'serving_name'    => 'grama',
                        'serving_size_g'  => 1,
                        'source'          => 'llm'
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("Erro na busca da API Externa: " . $e->getMessage());
        }

        return null;
    }

    private function estimateWithLLM(string $nomeAlimento)
    {
        try {
            $apiKey = config('services.groq.api_key');

            if (empty($apiKey)) {
                Log::error("Groq API Key não está configurada no arquivo de serviços.");
                return null;
            }

            // Forçando a URL absoluta direta para evitar o erro de scheme/host
            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um assistente especialista em nutrição. Responda APENAS com um objeto JSON válido, contendo a estimativa para 100g do alimento informado. Não use markdown blockcode (```json) na resposta. Chaves obrigatórias no JSON: name, protein_g, carbohydrate_g, fat_g, calories_kcal. Exemplo: {"name": "biscoito danix", "protein_g": 6.5, "carbohydrate_g": 68.0, "fat_g": 18.0, "calories_kcal": 460}'
                        ],
                        [
                            'role' => 'user',
                            'content' => "Estime os macronutrientes para 100g de: {$nomeAlimento}"
                        ]
                    ],
                    'response_format' => ['type' => 'json_object'], // Garante que a LLM cuspa JSON puro
                    'temperature' => 0.2
                ]);

            if ($response->successful()) {
                $llmData = json_decode($response->json()['choices'][0]['message']['content'] ?? '{}', true);

                // 1. Corrigido para dois dois-pontos (Sintaxe correta do PHP)
                Log::info("Resposta da LLM (Groq) para {$nomeAlimento}", ['llm_data' => $llmData]);

                if (!empty($llmData)) {
                    $proteina = (float)($llmData['protein_g'] ?? 0);
                    $carbo    = (float)($llmData['carbohydrate_g'] ?? 0);
                    $gordura  = (float)($llmData['fat_g'] ?? 0);
                    $caloria  = (float)($llmData['calories_kcal'] ?? 0);

                    // Cálculo matemático exato de backup por segurança
                    $caloria_backup = round(($proteina * 4) + ($carbo * 4) + ($gordura * 9), 0);

                    // Sistema imunológico: se a LLM mandar caloria zerada ou menor que a soma dos macros, aplica o backup
                    if ($caloria <= 0 || $caloria < ($proteina + $carbo + $gordura)) {
                        $caloria = $caloria_backup;
                    }

                    return [
                        'name'            => strtolower($llmData['name'] ?? $nomeAlimento),
                        'protein_g'       => round($proteina, 2),
                        'carbohydrate_g'  => round($carbo, 2),
                        'fat_g'           => round($gordura, 2),
                        'calories_kcal'   => round($caloria, 0),
                        'serving_name'    => 'grama',
                        'serving_size_g'  => 100, // 👈 Injetado para o seu motor de cálculo de porção
                        'source'          => 'llm_groq_estimate' // 👈 Injetado para identificar a origem do dado
                    ];
                }
            } else {
                Log::error("Groq retornou erro status {$response->status()}: " . $response->body());
            }
        } catch (\Throwable $t) {
            Log::error("Erro na estimativa do Groq: " . $t->getMessage(), [
                'file' => $t->getFile(),
                'line' => $t->getLine()
            ]);
        }

        return null;
    }
}
