<?php

namespace App\Http\Controllers;

use App\Services\TranscriptionService;
use App\Services\MealParserService;
use App\Services\NutritionManagerService;
use App\Services\TelegramService;
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
                $telegramService->sendMessage(
                    $request->input('message.chat.id'),
                    "👋 *Oi! Eu sou o Anotai.*\n" .
                    "──────────────────\n" .
                    "Me manda um áudio ou um texto contando o que você comeu que eu calculo os macros pra você.\n\n" .
                    "_Comandos ainda não são suportados._"
                );

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

    /**
     * Formata o retorno para o usuário final com uma UI limpa e scannable
     */
    private function formatarMensagemResposta(array $resultado)
    {
        Log::info('Formatando mensagem de resposta para o usuário', ['resultado' => $resultado]);
        $msg = "🍽️ *Resumo da Refeição*\n\n";

        foreach ($resultado['items'] as $item) {
            $msg .= "• " . ucwords($item['alimento']) . " — " . $item['quantidade'] . " " . $item['unidade'] . " · " . $item['calories_kcal'] . " kcal\n";
        }

        $msg .= "\n───────────────────\n";
        $msg .= "🔥 *Total:* " . $resultado['total_calories_kcal'] . " kcal\n";
        $msg .= "🍗 Proteína: " . $resultado['total_protein_g'] . "g   🍞 Carbo: " . $resultado['total_carbohydrate_g'] . "g   🥑 Gordura: " . $resultado['total_fat_g'] . "g";

        return $msg;
    }
}
