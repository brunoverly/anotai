<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserActionService;
use App\Services\TranscriptionService;
use App\Services\MealParserService;
use App\Services\MealReportService;
use App\Services\NutritionManagerService;
use App\Services\TelegramService;
use App\Services\UserMealService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TelegramController extends Controller
{
    public function receive(
        Request $request,
        TelegramService $telegramService,
        TranscriptionService $transcriptionService,
        NutritionManagerService $nutritionManagerService,
        UserMealService $userMealService,
        MealReportService $mealReportService,
        MealParserService $mealParserService)
    {
        $expectedSecret = config('services.telegram.webhook_secret');
        if (!empty($expectedSecret) && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $expectedSecret) {
            Log::warning('Webhook do Telegram recebido com secret token inválido ou ausente');
            return response()->json(['status' => 'forbidden'], 403);
        }

        if ($request->has('message.voice')) {
            log::info('Mensagem de voz recebida');

            try{
                $fileId = $request->input('message.voice.file_id');
                $userName = $request->input('message.from.username');

                $fileInfo = $telegramService->getFilePath($fileId);

                if (isset($fileInfo['ok']) && $fileInfo['ok']) {
                    log::info('Informações do arquivo de voz obtidas com sucesso', ['file_info' => $fileInfo]);

                    $filePath = $fileInfo['result']['file_path'];

                    $fileContent = $telegramService->downloadFile($filePath);

                    Storage::put('audios/' . $userName . '_' . $fileId . '.ogg', $fileContent);

                    $telegramService->sendMessage(
                        $request->input('message.chat.id'),
                        "📥 *Áudio Recebido!*\n" .
                        "──────────────────\n" .
                        "⏳ _Processando sua mensagem de voz..._\n" .
                        "🤖 _Aguarde um momento enquanto o Anotai faz a mágica acontecer._");

                    $transcribedText = $transcriptionService->transcribe('audios/' . $userName . '_' . $fileId . '.ogg');

                    if ($transcribedText) {
                        log::info('Transcrição concluída', ['transcribed_text' => $transcribedText]);

                        $response = $mealParserService->parseMealText($transcribedText);
                        if ($response) {
                            log::info('Análise de refeição concluída', ['parsed_meal' => $response]);

                            $mealData = $response;

                            // Validação de segurança para garantir que o formato veio correto
                            if (!isset($mealData['items']) || !is_array($mealData['items'])) {
                                Log::error("Formato de resposta inesperado da LLM", ['response' => $response]);

                                $telegramService->sendMessage(
                                $request->input('message.chat.id'),
                                    "⚠️ *Ops!*\nNão consegui estruturar os alimentos corretamente. Pode repetir, por favor?"
                                );
                            }
                            else{

                                $nutritionResponse = $nutritionManagerService->processarRefeicao($mealData['items']);

                                $telegramService->sendMessage(
                                    $request->input('message.chat.id'),
                                    $this->formatarMensagemResposta($nutritionResponse)
                                );

                                $userMealService->save(
                                    $nutritionResponse,
                                    (int) $request->input('message.chat.id'),
                                    $userName,
                                    $request->has('update_id') ? (int) $request->input('update_id') : null,
                                    $transcribedText
                                );

                            }
                        } else {
                            $telegramService->sendMessage(
                                $request->input('message.chat.id'),
                                "⚠️ *Erro na Análise!*\n" .
                                "──────────────────\n" .
                                "❌ _O Anotai não conseguiu analisar sua refeição. Por favor, tente novamente mais tarde._"
                            );
                        }
                    }

                    Storage::delete('audios/' . $userName . '_' . $fileId . '.ogg');

                } else {
                    $telegramService->sendMessage(
                        $request->input('message.chat.id'),
                        "⚠️ *Erro na Transcrição!*\n" .
                        "──────────────────\n" .
                        "❌ _O Anotai não conseguiu transcrever sua mensagem de voz. Por favor, tente novamente mais tarde._"
                    );

                    Log::error('Erro ao obter informações do arquivo de voz do Telegram', ['file_id' => $fileId, 'response' => $fileInfo]);
                }
            }catch (\Exception $e) {
                Log::error('Erro ao processar a mensagem do Telegram', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

                $telegramService->sendMessage(
                    $request->input('message.chat.id'),
                    "⚠️ *Erro Interno!*\n" .
                    "──────────────────\n" .
                    "❌ _O Anotai encontrou um erro inesperado. Por favor, tente novamente mais tarde._"
                );
            }
        }

        if ($request->has('message.text')) {
            log::info('Mensagem de texto recebida', ['text' => $request->input('message.text')]);

            $text = $request->input('message.text');

            if (str_starts_with(trim($text), '/')) {
                // Remove o "@nomedobot" que o Telegram às vezes anexa e pega só o comando
                $comando = strtolower(explode('@', explode(' ', trim($text))[0])[0]);
                $chatId = (int) $request->input('message.chat.id');

                if (in_array($comando, ['/hoje'])) {
                    $resumo = $mealReportService->resumoDia($chatId);
                    if(isset($resumo['user_calories_goal_kcal']))
                        $telegramService->sendMessage(
                            $request->input('message.chat.id'),
                            $this->formatarMensagemResumoComMacros($resumo, '📅 Resumo de Hoje')
                        );
                    else{
                         $telegramService->sendMessage(
                            $request->input('message.chat.id'),
                            $this->formatarMensagemResumo($resumo, '📅 Resumo de Hoje')
                        );
                    }
                } elseif ($comando === '/semana') {
                    $resumo = $mealReportService->resumoSemana($chatId);
                    if(isset($resumo['user_calories_goal_kcal']))
                        $telegramService->sendMessage(
                            $request->input('message.chat.id'),
                            $this->formatarMensagemResumoComMacros($resumo, '🗓️ Resumo da Semana')
                        );
                    else{
                        $telegramService->sendMessage(
                            $request->input('message.chat.id'),
                            $this->formatarMensagemResumo($resumo, '🗓️ Resumo da Semana')
                        );
                    }
                } else {
                    $telegramService->sendMessage(
                        $request->input('message.chat.id'),
                        "👋 *Oi! Eu sou o Anotai.*\n" .
                        "──────────────────\n" .
                        "Me manda um áudio ou um texto contando o que você comeu que eu calculo os macros pra você.\n\n" .
                        "*Comandos disponíveis:*\n" .
                        "/hoje — resumo do dia\n" .
                        "/semana — resumo da semana"
                    );
                }

                return response()->json(['status' => 'success']);
            }

            try{
                $telegramService->sendMessage(
                    $request->input('message.chat.id'),
                    "📥 *Texto Recebido!*\n" .
                    "──────────────────\n" .
                    "⏳ _Processando sua mensagem de texto..._\n" .
                    "🤖 _Aguarde um momento enquanto o Anotai faz a mágica acontecer._");


                    $response = $mealParserService->parseMealText($text);

                    if ($response) {
                        log::info('Análise de refeição concluída', ['parsed_meal' => $response]);

                        $mealData = $response;

                        Log::info('Dados da refeição extraídos', ['meal_data' => $mealData]);

                        if (!isset($mealData['items']) || !is_array($mealData['items'])) {
                            Log::error("Formato de resposta inesperado da LLM", ['response' => $response]);

                            $telegramService->sendMessage(
                            $request->input('message.chat.id'),
                                "⚠️ *Ops!*\nNão consegui estruturar os alimentos corretamente. Pode repetir, por favor?"
                            );
                        }
                        else{

                            $nutritionResponse = $nutritionManagerService->processarRefeicao($mealData['items']);

                            $telegramService->sendMessage(
                                $request->input('message.chat.id'),
                                $this->formatarMensagemResposta($nutritionResponse)
                            );

                            $userMealService->save(
                                $nutritionResponse,
                                (int) $request->input('message.chat.id'),
                                $request->input('message.from.username'),
                                $request->has('update_id') ? (int) $request->input('update_id') : null,
                                $text
                            );

                        }
                    } else {
                        $telegramService->sendMessage(
                            $request->input('message.chat.id'),
                            "⚠️ *Erro na Análise!*\n" .
                            "──────────────────\n" .
                            "❌ _O Anotai não conseguiu analisar sua refeição. Por favor, tente novamente mais tarde._"
                        );
                    }
            }catch (\Exception $e) {
                Log::error('Erro ao processar a mensagem de texto do Telegram', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

                $telegramService->sendMessage(
                    $request->input('message.chat.id'),
                    "⚠️ *Erro Interno!*\n" .
                    "──────────────────\n" .
                    "❌ _O Anotai encontrou um erro inesperado. Por favor, tente novamente mais tarde._"
                );
            }
        }


        return response()->json([
            'status' => 'success',
        ]);
    }

    private const UNIDADES_ABREVIADAS = [
        'grama' => 'g',
        'ml' => 'ml',
        'unidade' => 'un',
        'fatia' => 'fatia',
        'colher' => 'colher',
        'dose' => 'dose',
    ];

    private function formatarMensagemResumo(?array $resumo, string $titulo): string
    {
        if (!$resumo || $resumo['quantidade_refeicoes'] === 0) {
            $periodo = $resumo['periodo_formatado'] ?? null;
            $tituloComPeriodo = $periodo ? "{$titulo} ({$periodo})" : $titulo;

            return "{$tituloComPeriodo}\n──────────────────\nNenhuma refeição registrada ainda nesse período.";
        }

        $msg = "{$titulo} ({$resumo['periodo_formatado']})\n──────────────────\n";
        $msg .= "🍽️ {$resumo['quantidade_refeicoes']} refeição(ões) registrada(s)\n\n";
        $msg .= "🔥 *Total:* {$resumo['total_calories_kcal']} kcal\n";
        $msg .= "🥩 Proteína: {$resumo['total_protein_g']}g   🍞 Carbo: {$resumo['total_carbohydrate_g']}g   🥑 Gordura: {$resumo['total_fat_g']}g";

        return $msg;
    }

    /**
     * Só é chamada quando o $resumo já tem as 4 chaves de meta preenchidas
     * (a checagem de isset acontece antes, no ponto que chama esse método).
     */
    private function formatarMensagemResumoComMacros(array $resumo, string $titulo): string
    {
        if ($resumo['quantidade_refeicoes'] === 0) {
            return "{$titulo} ({$resumo['periodo_formatado']})\n──────────────────\nNenhuma refeição registrada ainda nesse período.";
        }

        $msg = "{$titulo} ({$resumo['periodo_formatado']})\n\n";

        $msg .= "🔥 Calorias: {$resumo['total_calories_kcal']} / " . round((float)$resumo['user_calories_goal_kcal']) . " kcal\n";
        $msg .= $this->barraDeProgresso((float)$resumo['total_calories_kcal'], (float)$resumo['user_calories_goal_kcal']) . "\n\n";

        $msg .= "🥩 Proteína: {$resumo['total_protein_g']}g / " . round((float)$resumo['user_protein_goal_g']) . "g\n";
        $msg .= $this->barraDeProgresso((float)$resumo['total_protein_g'], (float)$resumo['user_protein_goal_g'], eProteina: true) . "\n\n";

        $msg .= "🍞 Carbo: {$resumo['total_carbohydrate_g']}g / " . round((float)$resumo['user_carbohydrate_goal_g']) . "g\n";
        $msg .= $this->barraDeProgresso((float)$resumo['total_carbohydrate_g'], (float)$resumo['user_carbohydrate_goal_g']) . "\n\n";

        $msg .= "🥑 Gordura: {$resumo['total_fat_g']}g / " . round((float)$resumo['user_fat_goal_g']) . "g\n";
        $msg .= $this->barraDeProgresso((float)$resumo['total_fat_g'], (float)$resumo['user_fat_goal_g']) . "\n\n";

        $msg .= "📝 Refeições no período: {$resumo['quantidade_refeicoes']}\n";

        $dica = $this->gerarDicaProteina((float)$resumo['total_protein_g'], (float)$resumo['user_protein_goal_g'], $resumo['periodo']);
        if ($dica) {
            $msg .= "💡 {$dica}";
        }

        return rtrim($msg);
    }

    /**
     * Monta uma barra de 10 blocos representando o % da meta já consumido.
     * O número de blocos cheios é travado em 10 com min() — sem isso, passar
     * de 100% da meta faria "10 - blocosCheios" ficar negativo e o
     * str_repeat() dos blocos vazios quebraria com erro.
     */
    private function barraDeProgresso(float $consumido, float $meta, bool $eProteina = false): string
    {
        $percentual = $meta > 0 ? ($consumido / $meta) * 100 : 0;
        $blocosCheios = min(10, (int) round($percentual / 10));

        // Passou da meta: proteína a mais não é problema (amarelo, alerta leve),
        // os outros macros a mais tendem a ser indesejados (vermelho).
        $blocoCheio = '🟩';
        if ($percentual > 100) {
            $blocoCheio = $eProteina ? '🟨' : '🟥';
        }

        $barra = str_repeat($blocoCheio, $blocosCheios) . str_repeat('⬜', 10 - $blocosCheios);

        return "{$barra} (" . round($percentual) . "%)";
    }

    private function gerarDicaProteina(float $consumido, float $meta, string $periodo): ?string
    {
        $falta = $meta - $consumido;

        if ($falta <= 0) {
            return null;
        }

        $rotuloMeta = $periodo === 'semana' ? 'meta semanal' : 'meta diária';

        return "Faltam " . round($falta) . "g de proteína para bater a {$rotuloMeta}!";
    }

    /**
     * Formata o retorno para o usuário final com uma UI limpa e scannable
     */
    private function formatarMensagemResposta(array $resultado)
    {
        Log::info('Formatando mensagem de resposta para o usuário', ['resultado' => $resultado]);
        $msg = "🍽️ *Resumo da Refeição*\n\n";

        foreach ($resultado['items'] as $item) {
            $unidade = self::UNIDADES_ABREVIADAS[$item['unidade']] ?? $item['unidade'];
            $msg .= "• " . ucwords($item['alimento']) . " — " . $item['quantidade'] . " " . $unidade . " · " . $item['calories_kcal'] . " kcal\n";
        }

        $msg .= "\n───────────────────\n";
        $msg .= "🔥 *Total:* " . $resultado['total_calories_kcal'] . " kcal\n";
        $msg .= "🍗 Proteína: " . $resultado['total_protein_g'] . "g \n";
        $msg .= "🍞 Carbo: " . $resultado['total_carbohydrate_g'] . "g \n";
        $msg .= "🥑 Gordura: " . $resultado['total_fat_g'] . "g";

        return $msg;
    }
}
