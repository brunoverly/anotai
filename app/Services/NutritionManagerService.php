<?php

namespace App\Services;

use App\Models\Food;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $itemsNaoIdentificados = [];

        foreach ($items as $item) {
            $nomeAlimento = $item['alimento'] ?? '';
            $quantidadeInput = (float) ($item['quantidade'] ?? 0);
            $unidadeInput = $item['unidade'] ?? 'grama';
            $tipoAlimento = $item['tipo'] ?? null;

            // Chama a escada de busca para encontrar o alimento isolado
            $food = $this->searchFood($nomeAlimento, $tipoAlimento, $unidadeInput);

            if ($food) {
                // Regra de três com base nos dados encontrados
                $pesoTotalGrams = $quantidadeInput;

                // Se o input for em fatias/unidades e o banco/api tiver um tamanho de porção padrão
                if (!in_array($unidadeInput, ['grama', 'ml']) && (float)$food['serving_size_g'] > 0) {
                    $pesoTotalGrams = $quantidadeInput * (float)$food['serving_size_g'];
                }

                // Fator de multiplicação: todo alimento (local, API ou LLM) guarda
                // os macros por 100g/ml — $pesoTotalGrams já é o peso total em gramas.
                $fator = $pesoTotalGrams / 100;

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
                // Alimento totalmente não encontrado em nenhum degrau: não dá pra
                // afirmar com certeza o que é nem o peso, então NÃO inventamos um
                // valor zerado (isso já causou o incidente do "arroz com 1 kcal").
                // Fica de fora dos totais/itens processados e é reportado à parte.
                Log::warning("Alimento não identificado em nenhum degrau -> {$nomeAlimento}");
                $itemsNaoIdentificados[] = $nomeAlimento;
            }
        }

        return [
            'items' => $itemsProcessados,
            'items_nao_identificados' => $itemsNaoIdentificados,
            'total_protein_g' => round($totalProteinas, 2),
            'total_carbohydrate_g' => round($totalCarbos, 2),
            'total_fat_g' => round($totalGorduras, 2),
            'total_calories_kcal' => $totalCalorias
        ];
    }

    /**
     * A ESCADA DE BUSCA ISOLADA (Retorna um Array com os dados do alimento)
     */
    private function searchFood(string $nomeAlimento, ?string $tipoAlimento, string $unidadeInput)
    {
        // Sanitiza uma única vez aqui: todo o resto da escada (busca local,
        // nome salvo no cache do Degrau 2/3) usa essa mesma versão normalizada,
        // pra nunca ficar comparando/gravando "pão" e "pao" como coisas diferentes.
        $nomeAlimento = $this->sanitizeFoodName($nomeAlimento);

        // ── DEGRAU 1: BANCO LOCAL (Postgres/Supabase) ──
        $localFood = $this->searchInLocalDatabase($nomeAlimento);
        if ($localFood) {
            Log::info("Degrau 1: Alimento encontrado no banco local -> {$localFood->name}");

            $foodArray = $localFood->toArray();
            $foodArray['source'] = 'local'; // Tag para identificação no cálculo

            return $foodArray;
        }

        // ── DEGRAU 2: API EXTERNA (Open Food Facts) ──
        // A OFF é um catálogo de produtos de marca/código de barras: pra comida
        // in natura ou preparação caseira ela tende a "achar" o produto errado
        // (ver bug do "ovos"), então pulamos direto pro Degrau 3.
        if ($tipoAlimento === 'in_natura') {
            Log::info("Degrau 2: pulado (alimento in natura) -> {$nomeAlimento}");
        } else {
            Log::info("Degrau 2: Buscando na API Externa -> {$nomeAlimento}");
            $apiFood = $this->searchInExternalApi($nomeAlimento, $unidadeInput);
            if ($apiFood) {
                $newFood = Food::updateOrCreate(['name' => $apiFood['name']], $apiFood);

                return $newFood->toArray();
            }
        }

        // ── DEGRAU 3: ESTIMATIVA POR LLM (Groq) ──
        Log::info("Degrau 3: API falhou. Solicitando estimativa da LLM -> {$nomeAlimento}");
        $llmFood = $this->estimateWithLLM($nomeAlimento, $unidadeInput);
        if ($llmFood) {
            $newFood = Food::updateOrCreate(['name' => $llmFood['name']], $llmFood);

            return $newFood->toArray();
        }

        return null;
    }

    /**
     * Normaliza um nome de alimento pra comparação/gravação consistente:
     * minúsculas, sem acento (transliterado pra ASCII) e sem espaços
     * duplicados/nas pontas. Sem isso, "Pão", "pão" e "pao" (vindos de
     * chamadas diferentes de LLM/API) seriam tratados como alimentos
     * distintos tanto na busca quanto no cache salvo em `foods`.
     */
    private function sanitizeFoodName(string $nome): string
    {
        return (string) Str::of($nome)->lower()->ascii()->squish();
    }

    /**
     * Escapa os curingas do LIKE (% e _) pra que um nome de alimento que
     * contenha esses caracteres literalmente não seja interpretado como
     * padrão de busca.
     */
    private function escapeLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }

    private function searchInLocalDatabase(string $nomeAlimento)
    {
        Log::info("Buscando alimento no banco local -> {$nomeAlimento}");

        $exact = Food::where('name', $nomeAlimento)->first();
        if ($exact) {
            return $exact;
        }

        // Sem match exato: prioriza o nome mais curto (mais próximo do termo buscado)
        $found = Food::where('name', 'like', '%' . $this->escapeLike($nomeAlimento) . '%')
            ->orderByRaw('LENGTH(name) ASC')
            ->first();
        if ($found) {
            return $found;
        }

        // Plural em pt-BR geralmente é "+s" (ovos -> ovo, bananas -> banana):
        // tenta a forma singular antes de cair pra API externa.
        if (Str::endsWith($nomeAlimento, 's')) {
            $singular = Str::substr($nomeAlimento, 0, -1);

            return Food::where('name', $singular)
                ->orWhere('name', 'like', '%' . $this->escapeLike($singular) . '%')
                ->orderByRaw('LENGTH(name) ASC')
                ->first();
        }

        return null;
    }

    private function searchInExternalApi(string $nomeAlimento, string $unidadeInput)
    {
        try {
            // 1) Search-a-licious: busca com relevância de verdade (Elasticsearch),
            // ao contrário do endpoint legado cgi/search.pl que casa por palavra solta.
            $searchResponse = Http::timeout(15)
                ->get('https://search.openfoodfacts.org/search', [
                    'q'         => $nomeAlimento,
                    'page_size' => 1,
                    'langs'     => 'pt',
                ]);

            $hit = $searchResponse->successful() ? $searchResponse->json('hits.0') : null;

            if (!$hit || empty($hit['code'])) {
                Log::info("Degrau 2: nenhum resultado na busca -> {$nomeAlimento}");
                return null;
            }

            $nomeProduto = Str::lower($hit['product_name'] ?? '');
            $termoBusca = Str::lower($nomeAlimento);

            // Mesmo com relevância melhor, mantemos a checagem de sanidade:
            // se o nome do produto não tem nenhuma relação com o termo buscado, descarta.
            if (!Str::contains($nomeProduto, $termoBusca) && !Str::contains($termoBusca, $nomeProduto)) {
                Log::warning("Degrau 2: produto retornado não corresponde ao termo buscado, ignorando", [
                    'termo_buscado' => $nomeAlimento,
                    'produto_encontrado' => $hit['product_name'] ?? null,
                ]);

                return null;
            }

            // 2) A busca não devolve os nutrientes, só metadados de ranking — busca
            // os macros de verdade pelo código de barras no endpoint de produto.
            // Sem filtro de "fields": a API da OFF descarta product_quantity/
            // product_quantity_unit da resposta quando "nutriments" é pedido junto.
            $productResponse = Http::timeout(15)
                ->get("https://world.openfoodfacts.org/api/v2/product/{$hit['code']}.json");

            if (!$productResponse->successful() || $productResponse->json('status') !== 1) {
                Log::warning("Degrau 2: produto {$hit['code']} não encontrado na consulta detalhada");
                return null;
            }

            $product = $productResponse->json('product');
            $nutriments = $product['nutriments'] ?? [];

            // Se o usuário pediu em unidade/fatia/colher/dose, precisamos saber o peso
            // real disso (embalagem inteira ou porção declarada pelo fabricante) sem
            // perguntar nada pro usuário. Sem essa informação, deixamos cair pro
            // Degrau 3 (LLM), em vez de assumir "1 unidade = 1 grama" (bug antigo).
            $servingSizeG = 1;
            if (!in_array($unidadeInput, ['grama', 'ml'])) {
                $servingSizeG = $this->resolveServingSizeG($product, $unidadeInput);
                if ($servingSizeG === null) {
                    Log::info("Degrau 2: produto sem peso de embalagem/porção pra converter '{$unidadeInput}', deixando cair pro Degrau 3", [
                        'produto' => $product['product_name'] ?? null,
                    ]);
                    return null;
                }
            }

            Log::info("Alimento encontrado na API Externa -> {$product['product_name']}", ['nutriments' => $nutriments]);

            // Open Food Facts usa chaves no PLURAL ('proteins_100g', 'carbohydrates_100g')
            $proteina = $nutriments['proteins_100g'] ?? $nutriments['protein_100g'] ?? null;
            $carbo    = $nutriments['carbohydrates_100g'] ?? $nutriments['carbohydrate_100g'] ?? null;
            $gordura  = $nutriments['fat_100g'] ?? $nutriments['fats_100g'] ?? 0;
            $caloria  = $nutriments['energy-kcal_100g'] ?? $nutriments['energy_kcal_100g'] ?? null;
            $caloria_backup = round(((float)($proteina ?? 0) * 4) + ((float)($carbo ?? 0) * 4) + ((float)$gordura * 9), 0);

            // Se não achou caloria direta ou ela for menor que a soma dos macros, usa o cálculo de backup
            if ($caloria === null || $caloria < ($proteina + $carbo + $gordura)) {
                $caloria = $caloria_backup;
            }

            // Validação de segurança obrigatória
            if ($proteina !== null && $carbo !== null) {
                return [
                    'name'            => $this->sanitizeFoodName($product['product_name'] ?? $nomeAlimento),
                    'protein_g'       => round((float)$proteina, 2),
                    'carbohydrate_g'  => round((float)$carbo, 2),
                    'fat_g'           => round((float)$gordura, 2),
                    'calories_kcal'   => round((float)($caloria ?? 0), 0),
                    'serving_name'    => $unidadeInput,
                    'serving_size_g'  => $servingSizeG,
                    'source'          => 'openfoodfacts'
                ];
            }
        } catch (\Throwable $e) {
            Log::error("Erro na busca da API Externa: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Resolve o peso em gramas de "1 unidade/porção" do produto usando os dados
     * que a própria Open Food Facts já declara, sem precisar perguntar ao usuário.
     */
    private function resolveServingSizeG(array $product, string $unidadeInput): ?float
    {
        $pesoEmbalagem = $this->parseQuantityToGrams($product['product_quantity'] ?? null, $product['product_quantity_unit'] ?? null);
        $pesoPorcao = $this->parseQuantityToGrams($product['serving_quantity'] ?? null, $product['serving_quantity_unit'] ?? null);

        // "unidade" normalmente significa "a embalagem inteira" (ex: 1 danix, 1 iogurte)
        if ($unidadeInput === 'unidade') {
            return $pesoEmbalagem ?? $pesoPorcao;
        }

        // "fatia", "colher", "dose" se aproximam mais da porção declarada pelo fabricante
        return $pesoPorcao ?? $pesoEmbalagem;
    }

    private function parseQuantityToGrams($quantidade, ?string $unidade): ?float
    {
        if ($quantidade === null || $quantidade === '') {
            return null;
        }

        $valor = (float) $quantidade;

        return match (Str::lower($unidade ?? 'g')) {
            'kg', 'l' => $valor * 1000,
            'cl' => $valor * 10,
            default => $valor, // g, ml e unidades não reconhecidas tratamos como já-em-gramas
        };
    }

    /**
     * Pesos de referência (em gramas) por unidade de medida, pros alimentos mais
     * comuns de serem pedidos. Sem isso, a LLM tende a variar demais o peso que
     * considera "uma fatia"/"uma unidade" entre chamadas diferentes — já vimos
     * ela chutar 80g e 120g pra uma fatia de pão que na real pesa ~25g.
     */
    private function referenciaPesosPorUnidade(): string
    {
        return 'Use esta referência de pesos comuns como base, ajustando pelo alimento específico mencionado: '
            . 'FATIA: pão de forma (comum, integral, light ou multigrãos) 20-30g; pão francês 50g; queijo (mussarela, prato, minas) 15-25g; '
            . 'presunto, mortadela ou peito de peru 10-15g; pizza 100-150g; bolo 60-100g; melancia, melão ou abacaxi 100-150g. '
            . 'UNIDADE: ovo cozido/frito/mexido 45-55g; pão francês 50g; banana 90-120g; maçã, laranja ou pera 120-150g; batata média 130-150g; '
            . 'biscoito ou bolacha 5-10g; salsicha 50g; bombom ou chocolate pequeno 20-30g. '
            . 'COLHER (de sopa): arroz, feijão ou macarrão cozido 20-25g; açúcar, farinha ou aveia 12-15g; azeite, óleo ou manteiga 13g; maionese ou requeijão 15g. '
            . 'DOSE: whey protein ou suplemento em pó 30g; creatina 5g.';
    }

    private function estimateWithLLM(string $nomeAlimento, string $unidadeInput)
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
                            'content' => 'Você é um assistente especialista em nutrição. Responda APENAS com um objeto JSON válido, contendo a estimativa para 100g do alimento informado. Não use markdown blockcode (```json) na resposta. Chaves obrigatórias no JSON: name, protein_g, carbohydrate_g, fat_g, calories_kcal, peso_unidade_g. O campo peso_unidade_g é o peso estimado em gramas de EXATAMENTE 1 unidade de medida informada pelo usuário. ' . $this->referenciaPesosPorUnidade() . ' Se o alimento pedido não estiver na lista de referência, estime pelo tamanho físico típico de UMA porção daquela unidade — nunca chute o peso do alimento inteiro/embalagem quando a unidade pedida for uma fração menor (fatia/colher/dose). Se a unidade informada for "grama" ou "ml", o campo peso_unidade_g não será usado no cálculo — pode estimar o peso de uma porção média de refeição (ex: 150g). Exemplo, para a unidade "fatia" de biscoito danix: {"name": "biscoito danix", "protein_g": 6.5, "carbohydrate_g": 68.0, "fat_g": 18.0, "calories_kcal": 460, "peso_unidade_g": 40}'
                        ],
                        [
                            'role' => 'user',
                            'content' => "Estime os macronutrientes para 100g de: {$nomeAlimento}. A unidade de medida informada pelo usuário foi: \"{$unidadeInput}\"."
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
                    $pesoUnidade = (float)($llmData['peso_unidade_g'] ?? 100);

                    // Cálculo matemático exato de backup por segurança
                    $caloria_backup = round(($proteina * 4) + ($carbo * 4) + ($gordura * 9), 0);

                    // Sistema imunológico: se a LLM mandar caloria zerada ou menor que a soma dos macros, aplica o backup
                    if ($caloria <= 0 || $caloria < ($proteina + $carbo + $gordura)) {
                        $caloria = $caloria_backup;
                    }

                    return [
                        'name'            => $this->sanitizeFoodName($llmData['name'] ?? $nomeAlimento),
                        'protein_g'       => round($proteina, 2),
                        'carbohydrate_g'  => round($carbo, 2),
                        'fat_g'           => round($gordura, 2),
                        'calories_kcal'   => round($caloria, 0),
                        'serving_name'    => $unidadeInput,
                        'serving_size_g'  => round($pesoUnidade, 2) > 0 ? round($pesoUnidade, 2) : 100,
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
