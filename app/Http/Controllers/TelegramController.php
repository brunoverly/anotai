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
use Illuminate\Support\Facades\URL;
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

        // Garante que o usuário já exista no banco a partir da PRIMEIRA interação,
        // seja ela uma refeição ou um comando (ex: /macros) — antes disso, o
        // usuário só era criado ao salvar uma refeição, então comandos como
        // /macros falhavam silenciosamente pra quem nunca tinha registrado nada.
        if ($request->has('message.chat.id')) {
            $telegramUsername = $request->input('message.from.username');

            User::firstOrCreate(
                ['telegram_chat_id' => (int) $request->input('message.chat.id')],
                [
                    'name' => $telegramUsername ?? 'telegram_' . $request->input('message.chat.id'),
                    'telegram_username' => $telegramUsername,
                ]
            );
        }

        $msg_start = "👋 *Oi! Eu sou o Anotai.*\n" .
                    "──────────────────\n" .
                    "Me manda um áudio ou um texto contando o que você comeu que eu calculo os macros e mantenho o controle pra você.\n\n" .
                    "*Comandos disponíveis:*\n" .
                    "/dia — resumo do dia\n" .
                    "/semana — resumo da semana\n" .
                    "/excluir — exclui a última refeição do dia\n" .
                    "/macros — define suas metas de macros (calorias, carboidratos, proteínas)\n" .
                    '/app — link para o app web (dashboard)';

        if ($request->has('message.voice')) {
            log::info('Mensagem de voz recebida');

            $sentMessage = $telegramService->sendMessage(
                        $request->input('message.chat.id'),
                        "📥 *Áudio Recebido!*\n" .
                        "⏳ _Processando sua mensagem de voz..._\n" .
                        "🤖 _Aguarde um momento enquanto a mágica acontece._");
            $chat_msg_id = $sentMessage['result']['message_id'] ?? null;

            try{
                $fileId = $request->input('message.voice.file_id');
                $userName = $request->input('message.from.username');

                $fileInfo = $telegramService->getFilePath($fileId);

                if (isset($fileInfo['ok']) && $fileInfo['ok']) {
                    log::info('Informações do arquivo de voz obtidas com sucesso', ['file_info' => $fileInfo]);

                    $filePath = $fileInfo['result']['file_path'];

                    $fileContent = $telegramService->downloadFile($filePath);

                    Storage::put('audios/' . $userName . '_' . $fileId . '.ogg', $fileContent);


                    $transcribedText = $transcriptionService->transcribe('audios/' . $userName . '_' . $fileId . '.ogg');

                    if ($transcribedText) {
                        log::info('Transcrição concluída', ['transcribed_text' => $transcribedText]);

                        $response = $mealParserService->parseMealText($transcribedText);
                        if ($response) {
                            log::info('Análise de refeição concluída', ['parsed_meal' => $response]);

                            $mealData = $response;

                            // Validação de segurança: garante que o formato veio correto E que a LLM
                            // realmente identificou algum alimento (em vez de inventar um pra preencher).
                            if (!isset($mealData['items']) || !is_array($mealData['items'])) {
                                Log::error("Formato de resposta inesperado da LLM", ['response' => $response]);

                                $telegramService->editMessage(
                                    $request->input('message.chat.id'),
                                    $chat_msg_id,
                                    "⚠️ *Ops!*\nNão consegui estruturar os alimentos corretamente. Pode repetir, por favor?"
                                );
                            } elseif (empty($mealData['items'])) {
                                Log::info("Nenhum alimento identificado no áudio", ['transcribed_text' => $transcribedText]);

                                $telegramService->editMessage(
                                    $request->input('message.chat.id'),
                                    $chat_msg_id,
                                    "🤔 *Não identifiquei nenhum alimento*\n" .
                                    "──────────────────\n" .
                                    "Não consegui reconhecer o que você comeu nesse áudio. Pode tentar descrever de novo, com mais detalhes?"
                                );
                            }
                            else{

                                $nutritionResponse = $nutritionManagerService->processarRefeicao($mealData['items']);

                                if (!empty($nutritionResponse['items_nao_identificados'])) {
                                    Log::warning("Alimentos não identificados no áudio", ['itens' => $nutritionResponse['items_nao_identificados']]);

                                    $telegramService->editMessage(
                                        $request->input('message.chat.id'),
                                        $chat_msg_id,
                                        $this->formatarMensagemNaoIdentificado($nutritionResponse['items_nao_identificados'])
                                    );
                                } else {
                                    $telegramService->editMessage(
                                        $request->input('message.chat.id'),
                                        $chat_msg_id,
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

                            }
                        } else {
                            $telegramService->editMessage(
                                $request->input('message.chat.id'),
                                $chat_msg_id,
                                "⚠️ *Erro na Análise!*\n" .
                                "──────────────────\n" .
                                "❌ _O Anotai não conseguiu analisar sua refeição. Por favor, tente novamente mais tarde._"
                            );
                        }
                    }

                    Storage::delete('audios/' . $userName . '_' . $fileId . '.ogg');

                } else {
                    $telegramService->editMessage(
                        $request->input('message.chat.id'),
                        $chat_msg_id,
                        "⚠️ *Erro na Transcrição!*\n" .
                        "──────────────────\n" .
                        "❌ _O Anotai não conseguiu transcrever sua mensagem de voz. Por favor, tente novamente mais tarde._"
                    );

                    Log::error('Erro ao obter informações do arquivo de voz do Telegram', ['file_id' => $fileId, 'response' => $fileInfo]);
                }
            }catch (\Exception $e) {
                Log::error('Erro ao processar a mensagem do Telegram', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $telegramService->editMessage(
                    $request->input('message.chat.id'),
                    $chat_msg_id,
                    "⚠️ *Erro Interno!*\n" .
                    "──────────────────\n" .
                    "❌ _O Anotai encontrou um erro inesperado. Por favor, tente novamente mais tarde._"
                );
            }
        }

        if ($request->has('message.text')) {
            log::info('Mensagem de texto recebida', ['text' => $request->input('message.text')]);

            $sentMessage = $telegramService->sendMessage(
                    $request->input('message.chat.id'),
                    "📥 *Texto Recebido!*\n" .
                    "⏳ _Processando sua mensagem de texto..._\n" .
                    "🤖 _Aguarde um momento enquanto a mágica acontece._");
            $chat_msg_id = $sentMessage['result']['message_id'] ?? null;

            $text = $request->input('message.text');
            $macros = $this->checkUserMacroRegex($text);

            if ($macros) {
                    $response =$mealReportService->saveUserMacros((int) $request->input('message.chat.id'), $macros);

                    if ($response) {
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            "✅ *Metas de Macros Atualizadas!*\n" .
                            "──────────────────\n" .
                            "Suas metas foram atualizadas com sucesso:\n" .
                            "🔥 Calorias: {$macros['calories_kcal']} kcal\n" .
                            "🍞 Carboidratos: {$macros['carbohydrate_g']} g\n" .
                            "🥩 Proteínas: {$macros['protein_g']} g\n" .
                            "🥑 Gorduras: {$macros['fat_g']} g \n\n".
                            "Agora os comandos /hoje e /semana mostrarão seu progresso."
                        );
                    } else {
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            "⚠️ *Erro ao Atualizar Metas!*\n" .
                            "──────────────────\n" .
                            "❌ _O Anotai não conseguiu atualizar suas metas de macros. Por favor, verifique suas informações e tente novamente._"
                        );
                    }

                return response()->json(['status' => 'success']);
            }

            if (str_starts_with(trim($text), '/')) {
                // Remove o "@nomedobot" que o Telegram às vezes anexa e pega só o comando
                $comando = strtolower(explode('@', explode(' ', trim($text))[0])[0]);
                $chatId = (int) $request->input('message.chat.id');

                if (in_array($comando, ['/dia'])) {
                    $resumo = $mealReportService->resumoDia($chatId);

                    if(isset($resumo['user_calories_goal_kcal']))
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            $this->formatarMensagemResumoComMacros($resumo, '📅 Resumo de Hoje')
                        );
                    else{
                         $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            $this->formatarMensagemResumo($resumo, '📅 Resumo de Hoje')
                        );
                    }
                } elseif ($comando === '/semana') {
                    $resumo = $mealReportService->resumoSemana($chatId);
                    if(isset($resumo['user_calories_goal_kcal']))
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            $this->formatarMensagemResumoComMacros($resumo, '🗓️ Resumo da Semana')
                        );
                    else{
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            $this->formatarMensagemResumo($resumo, '🗓️ Resumo da Semana')
                        );
                    }
                } elseif ($comando === '/excluir') {
                    $deletedMeal = $mealReportService->excluirUltimaRefeicao($chatId);
                    if($deletedMeal) {
                        $msg = $this->formatarMensagemResposta($deletedMeal);
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            "🗑️ *Última Refeição Excluída!*\n" .
                            "──────────────────\n" .
                            $msg
                        );
                    } else {
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            "⚠️ *Nenhuma Refeição Encontrada!*\n" .
                            "──────────────────\n" .
                            "Não foi encontrada uma refeição registrada hoje para excluir."
                        );
                    }

                } elseif ($comando === '/macros'){
                    $telegramService->editMessage(
                        $request->input('message.chat.id'),
                        $chat_msg_id,
                        "🎯  *Metas de Macros*\n" .
                        "──────────────────\n" .
                        "Copie esta mensagem e preencha dentro das \\[ \\] os valores das suas metas:\n" .
                        "*somente os valores numéricos, sem virgulas ou pontos.*\n" .
                        "🔥 Calorias: \\[   \\] kcal\n" .
                        "🍞 Carboidratos: \\[   \\] g\n" .
                        "🥩 Proteínas: \\[   \\] g\n" .
                        "🥑 Gorduras: \\[   \\] g"
                    );
                } elseif ($comando === '/app') {
                    $dashboardUrl = URL::temporarySignedRoute('dashboard.login', now()->addMinutes(10), ['chatId' => $chatId]);
                    $telegramService->editMessage(
                        $request->input('message.chat.id'),
                        $chat_msg_id,
                        "📈 *Dashboard Anotai*\n" .
                        "──────────────────\n" .
                        "Acompanhe seus macros, refeições e metas pelo painel web:\n" .
                        "[Abrir Dashboard]({$dashboardUrl})"
                    );
                }else {
                    $telegramService->editMessage(
                        $request->input('message.chat.id'),
                        $chat_msg_id,
                        $msg_start
                    );
                }

                return response()->json(['status' => 'success']);
            }

            try{
                    $response = $mealParserService->parseMealText($text);

                    if ($response) {
                        log::info('Análise de refeição concluída', ['parsed_meal' => $response]);

                        $mealData = $response;

                        Log::info('Dados da refeição extraídos', ['meal_data' => $mealData]);

                        if (!isset($mealData['items']) || !is_array($mealData['items'])) {
                            Log::error("Formato de resposta inesperado da LLM", ['response' => $response]);

                            $telegramService->editMessage(
                                $request->input('message.chat.id'),
                                $chat_msg_id,
                                "⚠️ *Ops!*\nNão consegui estruturar os alimentos corretamente. Pode repetir, por favor?"
                            );
                        } elseif (empty($mealData['items'])) {
                            Log::info("Nenhum alimento identificado no texto", ['text' => $text]);

                            $telegramService->editMessage(
                                $request->input('message.chat.id'),
                                $chat_msg_id,
                                "🤔 *Não identifiquei nenhum alimento*\n" .
                                "──────────────────\n" .
                                "Não consegui reconhecer o que você comeu nessa mensagem. Pode tentar descrever de novo, com mais detalhes?"
                            );
                        }
                        else{

                            $nutritionResponse = $nutritionManagerService->processarRefeicao($mealData['items']);

                            if (!empty($nutritionResponse['items_nao_identificados'])) {
                                Log::warning("Alimentos não identificados no texto", ['itens' => $nutritionResponse['items_nao_identificados']]);

                                $telegramService->editMessage(
                                    $request->input('message.chat.id'),
                                    $chat_msg_id,
                                    $this->formatarMensagemNaoIdentificado($nutritionResponse['items_nao_identificados'])
                                );
                            } else {
                                $telegramService->editMessage(
                                    $request->input('message.chat.id'),
                                    $chat_msg_id,
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

                        }
                    } else {
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chat_msg_id,
                            "⚠️ *Erro na Análise!*\n" .
                            "──────────────────\n" .
                            "❌ _O Anotai não conseguiu analisar sua refeição. Por favor, tente novamente mais tarde._"
                        );
                    }
            }catch (\Exception $e) {
                Log::error('Erro ao processar a mensagem de texto do Telegram', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

                $telegramService->editMessage(
                    $request->input('message.chat.id'),
                    $chat_msg_id,
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
            return "```\n{$titulo} {$resumo['periodo_formatado']}\n```\nNenhuma refeição registrada ainda nesse período.";
        }

        $msg = "```\n{$titulo} {$resumo['periodo_formatado']}\n```\n\n";

        // Situação acumula os avisos de "acima da meta" — proteína a mais não é
        // problema, então ela nunca entra na lista, mesmo passando de 100%.
        $situacao = [];

        $msg .= $this->blocoMacro('🔥', 'Calorias', (float)$resumo['total_calories_kcal'], (float)$resumo['user_calories_goal_kcal'], 'kcal', true, $situacao);
        $msg .= $this->blocoMacro('🥩', 'Proteínas', (float)$resumo['total_protein_g'], (float)$resumo['user_protein_goal_g'], 'g', false, $situacao);
        $msg .= $this->blocoMacro('🍞', 'Carboidratos', (float)$resumo['total_carbohydrate_g'], (float)$resumo['user_carbohydrate_goal_g'], 'g', true, $situacao);
        $msg .= $this->blocoMacro('🥑', 'Gorduras', (float)$resumo['total_fat_g'], (float)$resumo['user_fat_goal_g'], 'g', true, $situacao);

        if (!empty($situacao)) {
            $msg .= "📌 *Situação*\n" . implode("\n", $situacao);
        }

        return rtrim($msg);
    }

    /**
     * Monta o bloco de uma macro: rótulo, "consumido / meta unidade" e a barra
     * de 10 marcadores (●/○). O número de marcadores cheios é travado em 10
     * com min() — sem isso, passar de 100% da meta faria "10 - blocosCheios"
     * ficar negativo e o str_repeat() dos marcadores vazios quebraria com erro.
     *
     * $alertaSeAcima controla a cor do marcador quando passa de 100%: 🔴 pra
     * quem é ruim passar (calorias, carbo, gordura) e 🟢 pra quem tanto faz
     * ou é até bom (proteína) — só calorias/carbo/gordura entram na lista de
     * "Situação", proteína nunca vira alerta mesmo passando da meta.
     */
    private function blocoMacro(string $emoji, string $label, float $consumido, float $meta, string $unidade, bool $alertaSeAcima, array &$situacao): string
    {
        $percentual = $meta > 0 ? ($consumido / $meta) * 100 : 0;
        $blocosCheios = min(10, (int) round($percentual / 10));
        $barra = str_repeat('●', $blocosCheios) . str_repeat('○', 10 - $blocosCheios);

        $marcador = '';
        $diferenca = '';
        if ($percentual > 100) {
            $statusEmoji = $alertaSeAcima ? '🔴' : '🟢';
            $marcador = " {$statusEmoji}";
            $diferenca = ' _(+' . round($consumido - $meta) . " {$unidade})_";

            if ($alertaSeAcima) {
                $situacao[] = "• 🔴 {$label} acima da meta.";
            } else {
                $situacao[] = "• 🟢 Meta de {$label} atingida!";
            }
        }

        return "{$emoji} *{$label}*\n" .
            " {$consumido} / " . round($meta) . " {$unidade}{$marcador}{$diferenca}\n" .
            " {$barra} " . round($percentual) . "%\n\n";
    }

    /**
     * Alimento(s) que passaram pela escada de busca (banco local, API externa
     * e LLM) sem que nenhum degrau conseguisse identificar com certeza o que
     * é nem o peso — em vez de salvar um valor inventado/zerado, avisa o
     * usuário e pede pra descrever de novo.
     */
    private function formatarMensagemNaoIdentificado(array $nomesNaoIdentificados): string
    {
        $lista = implode(', ', array_map('ucwords', $nomesNaoIdentificados));

        return "⚠️ *Não consegui identificar com certeza*\n" .
            "──────────────────\n" .
            "Não consegui reconhecer o alimento ou o peso de: *{$lista}*.\n\n" .
            "💡 Funciono melhor quando você informa o peso em gramas (ex: \"150g de arroz\"). Pode tentar de novo?";
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
        $msg .= "🥩 Proteína: " . $resultado['total_protein_g'] . "g \n";
        $msg .= "🍞 Carbo: " . $resultado['total_carbohydrate_g'] . "g \n";
        $msg .= "🥑 Gordura: " . $resultado['total_fat_g'] . "g";

        return $msg;
    }

    /**
     * Verifica se a mensagem recebida é uma resposta preenchida do template de /macros.
     * Retorna null se algum dos 4 valores não for encontrado (ou seja, não é uma resposta válida).
     */
    private function checkUserMacroRegex($msg)
    {
        $padroes = [
            'calories_kcal'    => '/Calorias:\s*\[?\s*(\d+)\s*\]?\s*kcal/iu',
            'carbohydrate_g'   => '/Carboidratos:\s*\[?\s*(\d+)\s*\]?\s*g/iu',
            'protein_g'        => '/Prote[íi]nas:\s*\[?\s*(\d+)\s*\]?\s*g/iu',
            'fat_g'            => '/Gorduras:\s*\[?\s*(\d+)\s*\]?\s*g/iu',
        ];

        $macros = [];

        foreach ($padroes as $chave => $padrao) {
            if (!preg_match($padrao, $msg, $matches)) {
                return false;
            }

            $macros[$chave] = (int) $matches[1];
        }

        return $macros;
    }
}
