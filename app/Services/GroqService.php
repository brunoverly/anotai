<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Services\NutritionManagerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GroqService
{
    public function textToJson(string $text)
    {
        $apiKey = config('services.groq.api_key');

        $prompt = "Você é um assistente de nutrição estruturado. Sua única função é extrair alimentos de frases ditas pelo usuário.
            Retorne ESTRITAMENTE um objeto JSON contendo uma chave 'items', que será um array de objetos. Cada objeto deve seguir este formato exato:
            {
            \"alimento\": \"nome do alimento em minúsculas\",
            \"quantidade\": número ou float representando a quantidade consumida,
            \"unidade\": \"a unidade de medida estrita mencionada ou deduzida\",
            \"tipo\": \"'in_natura' ou 'industrializado'\"
            }

            As únicas opções aceitáveis para o campo 'unidade' são: 'unidade', 'fatia', 'colher', 'dose', 'grama', 'ml'.
            - O campo 'alimento' deve manter TODOS os qualificadores que o usuário mencionou (tipo, variedade, marca, preparo). NUNCA simplifique ou generalize o nome: se o usuário disse 'queijo prato', mantenha 'queijo prato' (não vire 'queijo'); se disse 'pão de forma integral', mantenha 'pão de forma integral' (não vire 'pão'). Os únicos ajustes permitidos são deixar em minúsculas e SEMPRE escrever o nome do alimento no singular, mesmo que o usuário tenha falado no plural (ex: 'comi 5 ovos' -> \"alimento\": \"ovo\", \"quantidade\": 5; 'feijões' -> 'feijão'; 'pães' -> 'pão') — a quantidade já fica registrada no campo 'quantidade', então o plural no nome só atrapalha a busca depois.
            - Se o usuário não disser a quantidade ou unidade de alimentos comerciais comuns (ex: 'comi um snickers', 'mandei um whey'), deduza quantidade 1 e coloque a unidade correta ('unidade' ou 'dose').
            - Se for um alimento de peso (ex: '100g de arroz'), defina a quantidade como 100 e a unidade como 'grama'.
            - Se o usuário mencionar pratos compostos, receitas tradicionais ou combinações que formam uma única preparação, trate como um ÚNICO objeto dentro do array 'items' — mesmo quando a descrição lista 2 ou mais ingredientes ligados por \"e\" (ex: 'escondidinho de carne seca com mandioca', 'frango com quiabo', 'pão com manteiga', 'arroz com feijão', 'sanduíche de presunto e queijo', 'omelete com tomate e cebola'). O padrão \"sanduíche/salada/omelete/torta/macarrão ... com X e Y\" descreve o RECHEIO ou a COMPOSIÇÃO de UM prato só — NUNCA quebre \"X\" e \"Y\" em objetos separados nesse caso. Só crie itens distintos quando o usuário claramente comeu coisas independentes, não quando um item é ingrediente/recheio de outro já citado (ex: 'comi um sanduíche e uma banana' -> dois itens, pois são alimentos totalmente separados, nenhum é recheio do outro). IMPORTANTE: o campo \"alimento\" desse item único tem que manter a frase completa, incluindo o \"com X e Y\" — NUNCA corte o recheio/composição do nome (ex: 'comi um sanduíche misto quente com presunto e queijo' -> \"alimento\": \"sanduíche misto quente com presunto e queijo\", NUNCA apenas \"sanduíche misto quente\"), senão a busca depois perde a especificidade do prato.
            - O campo 'tipo' deve ser 'industrializado' SOMENTE quando o alimento for claramente um produto de marca/embalado (ex: 'danix', 'nescau', 'leite ninho', 'coca-cola', 'whey growth'). Comida in natura, preparações caseiras, pratos compostos, frutas, carnes, grãos e legumes (mesmo com nome composto, como 'escondidinho de carne seca') devem ser 'in_natura'.
            - Se o texto não mencionar nenhum alimento ou refeição reconhecível (ex: cumprimentos, perguntas, comandos, texto sem sentido ou incompreensível), retorne \"items\": [] (array vazio). NUNCA invente ou chute um alimento só para preencher o array — é preferível retornar vazio a errar o que a pessoa comeu.
            - Nunca adicione textos explicativos ou Markdown. Retorne apenas o objeto JSON puro.

            Texto do usuário: \"{$text}\"";

        $response = Http::withToken($apiKey)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-8b-instant',
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $prompt
                    ],
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

    public function audioToJson(string $relativeFilePath)
    {
        $apiKey = config('services.groq.api_key');
        $absolutePath = Storage::path($relativeFilePath);

        if (!file_exists($absolutePath)) {
            Log::error("Arquivo não encontrado para transcrição: {$absolutePath}");
            return null;
        }

        $response = Http::withToken($apiKey)
            ->attach(
                'file',
                file_get_contents($absolutePath),
                basename($absolutePath)
            )
            ->post('https://api.groq.com/openai/v1/audio/transcriptions', [
                'model' => 'whisper-large-v3-turbo',
                'language' => 'pt',
            ]);

        if ($response->successful()) {
            return $response->json('text');
        }

        Log::error('Falha na API de Transcrição Groq', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return false;
    }

    public function getNutricionInfo(
        string $food,
        string $inputUnity)
    {
        $servingSizeReference =  'Use esta lista como GUIA DE ORDEM DE GRANDEZA para a unidade informada, adaptando o peso real ao alimento solicitado: '
        . 'FATIA: pão de forma/integral 25-30g; queijo/frio 15-20g; pizza/torta 100-150g; bolo 60-80g; frutas (melancia/abacaxi) 100-150g. '
        . 'UNIDADE: pão francês 50g; ovo 50g; banana 90-110g; maçã/laranja 130-150g; batata média 140g; biscoito 8-10g; salgado de lanchonete (coxinha/empada) 80-120g; hambúrguer (disco) 90-120g; chocolate pequeno 25g. '
        . 'COLHER (sopa): arroz/feijão/massa 25g; farinha/aveia/açúcar 12-15g; azeite/óleo/manteiga 12g; requeijão/maionese 15g; pasta de amendoim 15g. '
        . 'DOSE/CONCHA: whey protein 30g; creatina 5g; concha de feijão/sopa 100-130g.';

        try {
            $apiKey = config('services.groq.api_key');

            if (empty($apiKey)) {
                Log::error("Groq API Key não está configurada no arquivo de serviços.");
                return null;
            }

            $response = Http::withToken($apiKey)
                ->timeout(10)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Você é um assistente especialista em nutrição e bioquímica de alimentos. Sua principal regra é a precisão matemática rigorosa: as calories_kcal devem ser exatamente a soma de (protein_g x 4) + (carbohydrate_g x 4) + (fat_g x 9). Responda APENAS com um objeto JSON válido, contendo a estimativa para 100g do alimento informado. Não use markdown blockcode ou qualquer outro texto na resposta. Chaves obrigatórias no JSON: name, protein_g, carbohydrate_g, fat_g, calories_kcal, peso_unidade_g. O campo peso_unidade_g é o peso estimado em gramas de EXATAMENTE 1 unidade média padrão de consumo do item. ' . $servingSizeReference . ' Se o alimento pedido não estiver na lista de referência, estime pelo tamanho físico típico de UMA porção comercial padrão daquela unidade (ex: salgados de lanchonete devem ter peso unitário realista, nunca de festa, a menos que especificado). Se a unidade informada for "grama" ou "ml", o campo peso_unidade_g não será usado no cálculo — pode estimar o peso de uma porção média de refeição (ex: 150g). Exemplos corretos com proporções e calorias matematicamente alinhadas para 100g: {"name": "pao de queijo tradicional", "protein_g": 6.0, "carbohydrate_g": 32.0, "fat_g": 16.0, "calories_kcal": 300, "peso_unidade_g": 30} e {"name": "abacate", "protein_g": 2.0, "carbohydrate_g": 8.5, "fat_g": 14.7, "calories_kcal": 160, "peso_unidade_g": 200}'
                        ],
                        [
                            'role' => 'user',
                            'content' => "Estime os macronutrientes para 100g de: {$food}. A unidade de medida informada pelo usuário foi: \"{$inputUnity}\"."
                        ]
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.2
                ]);

            if ($response->successful()) {
                $llmData = json_decode($response->json()['choices'][0]['message']['content'] ?? '{}', true);

                Log::info("Resposta da LLM (Groq) para {$food}", ['llm_data' => $llmData]);

                if (!empty($llmData)) {
                    $protein = (float)($llmData['protein_g'] ?? 0);
                    $carb    = (float)($llmData['carbohydrate_g'] ?? 0);
                    $fat  = (float)($llmData['fat_g'] ?? 0);
                    $calories  = (float)($llmData['calories_kcal'] ?? 0);
                    $servingSize = (float)($llmData['peso_unidade_g'] ?? 100);

                    $backupCalories = round(($protein * 4) + ($carb * 4) + ($fat * 9), 0);

                    $diff = $backupCalories > 0 ? abs($calories - $backupCalories) / $backupCalories : 0;
                    if ($calories <= 0 || $diff > 0.15) {
                        $calories = $backupCalories;
                    }

                    return [
                        'name'            => NutritionManagerService::sanitizeFoodName($llmData['name'] ?? $food),
                        'protein_g'       => round($protein, 2),
                        'carbohydrate_g'  => round($carb, 2),
                        'fat_g'           => round($fat, 2),
                        'calories_kcal'   => round($calories, 0),
                        'serving_size_g'  => round($servingSize, 2) > 0 ? round($servingSize, 2) : 100,
                        'source'          => 'llm_groq_estimate'
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
