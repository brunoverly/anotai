<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MealParserService
{
    public function parseMealText(string $text)
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
            - Se o usuário mencionar pratos compostos, receitas tradicionais ou combinações que formam uma única preparação (ex: 'escondidinho de carne seca com mandioca', 'frango com quiabo', 'pão com manteiga', 'arroz com feijão'), trate como um ÚNICO objeto dentro do array 'items'. Não separe os ingredientes que dão nome ao prato em múltiplos itens.
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
}
