<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\MessageService;
use App\Services\GroqService;
use App\Services\MealService;
use App\Services\NutritionManagerService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class TelegramController extends Controller
{
    public function receive(
        Request $request,
        TelegramService $telegramService,
        NutritionManagerService $nutritionManagerService,
        MealService $mealReportService,
        MessageService $messageService,
        GroqService $groqService)
    {
        $expectedSecret = config('services.telegram.webhook_secret');
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (empty($expectedSecret) || !hash_equals($expectedSecret, $providedSecret)) {
            Log::warning('Webhook do Telegram recusado: secret token ausente, inválido, ou não configurado no servidor');
            return response()->json(['status' => 'forbidden'], 403);
        }


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

        if ($request->has('message.voice')) {
            Log::info('Mensagem de voz recebida', ['chat_id' => $request->input('message.chat.id')]);

            try {
                $sentMessage = $telegramService->sendMessage(
                            $request->input('message.chat.id'),
                            $messageService->getAudioReceivedMessage());

                $chatMessageId = $sentMessage['result']['message_id'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('Falha de rede ao enviar confirmação de recebimento (áudio), seguindo sem message_id', ['exception' => $e->getMessage()]);
                $chatMessageId = null;
            }

            try{
                $fileId = $request->input('message.voice.file_id');
                $userName = $request->input('message.from.username');

                $safeFileName = preg_replace('/[^A-Za-z0-9_]/', '', (string) $userName) ?: 'anonimo';
                $safeFileId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $fileId);
                $audioPath = 'audios/' . $safeFileName . '_' . $safeFileId . '.ogg';

                $fileInfo = $telegramService->getFilePath($fileId);

                if (isset($fileInfo['ok']) && $fileInfo['ok']) {
                    $filePath = $fileInfo['result']['file_path'];

                    $fileContent = $telegramService->downloadFile($filePath);

                    Storage::put($audioPath, $fileContent);

                    $transcribedText = $groqService->audioToJson($audioPath);

                    if ($transcribedText) {
                        Log::info('Transcrição concluída', ['transcribed_text' => $transcribedText]);

                        $response = $groqService->textToJson($transcribedText);

                        if ($response) {
                            $mealData = $response;

                            Log::info('Análise de refeição concluída (áudio)', ['itens' => array_column($mealData['items'] ?? [], 'alimento')]);

                            if (!isset($mealData['items']) || !is_array($mealData['items'])) {
                                Log::warning('Formato de resposta inesperado da LLM ao interpretar áudio', ['response' => $response]);

                                $telegramService->editMessage(
                                    $request->input('message.chat.id'),
                                    $chatMessageId,
                                    $messageService->getErrorParsingJsonMessage()
                                );
                            } elseif (empty($mealData['items'])) {
                                Log::warning('Nenhum alimento identificado no áudio', ['transcribed_text' => $transcribedText]);

                                $telegramService->editMessage(
                                    $request->input('message.chat.id'),
                                    $chatMessageId,
                                    $messageService->getErrorParsingJsonMessage()
                                );
                            }
                            else{

                                $nutritionResponse = $nutritionManagerService->getNutritionInfo($mealData['items']);

                                if (!empty($nutritionResponse['items_nao_identificados'])) {
                                    Log::warning("Alimentos não identificados no áudio", ['itens' => $nutritionResponse['items_nao_identificados']]);

                                    $telegramService->editMessage(
                                        $request->input('message.chat.id'),
                                        $chatMessageId,
                                        $messageService->getErrorParsingJsonMessage()
                                    );
                                } else {
                                    $telegramService->editMessage(
                                        $request->input('message.chat.id'),
                                        $chatMessageId,
                                        $messageService->defaultResponseMessage($nutritionResponse)
                                    );

                                    $savedMeal = $mealReportService->save(
                                        $nutritionResponse,
                                        (int) $request->input('message.chat.id'),
                                        $userName,
                                        $request->has('update_id') ? (int) $request->input('update_id') : null,
                                        $transcribedText
                                    );

                                    Log::info('Refeição salva com sucesso', ['meal_id' => $savedMeal->id, 'chat_id' => $request->input('message.chat.id'), 'total_calories_kcal' => $nutritionResponse['total_calories_kcal']]);
                                }

                            }
                        } else {
                            $telegramService->editMessage(
                                $request->input('message.chat.id'),
                                $chatMessageId,
                                $messageService->getInternalErrorMessage()
                            );
                        }
                    }

                    Storage::delete($audioPath);

                } else {
                    $telegramService->editMessage(
                        $request->input('message.chat.id'),
                        $chatMessageId,
                        $messageService->getInternalErrorMessage()
                    );

                    Log::error('Erro ao obter informações do arquivo de voz do Telegram', ['file_id' => $fileId, 'response' => $fileInfo]);
                }
            }catch (\Exception $e) {
                Log::error('Erro ao processar a mensagem do Telegram', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                $telegramService->editMessage(
                    $request->input('message.chat.id'),
                    $chatMessageId,
                    $messageService->getInternalErrorMessage()
                );
            }
        }

        if ($request->has('message.text')) {
            Log::info('Mensagem de texto recebida', ['chat_id' => $request->input('message.chat.id'), 'text' => $request->input('message.text')]);

            try {
                $sentMessage = $telegramService->sendMessage(
                        $request->input('message.chat.id'),
                        $messageService->getTextReceivedMessage()
                );

                $chatMessageId = $sentMessage['result']['message_id'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('Falha de rede ao enviar confirmação de recebimento (texto), seguindo sem message_id', ['exception' => $e->getMessage()]);
                $chatMessageId = null;
            }

            $text = $request->input('message.text');
            $macros = $this->checkUserMacroRegex($text);

            if ($macros) {
                    $response =$mealReportService->saveUserMacros((int) $request->input('message.chat.id'), $macros);

                    if ($response) {
                        Log::info('Metas de macros atualizadas', ['chat_id' => $request->input('message.chat.id'), 'macros' => $macros]);

                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            $messageService->getMacrosUpdateMessage($macros)
                        );
                    } else {
                        Log::warning('Falha ao atualizar metas de macros: usuário não encontrado', ['chat_id' => $request->input('message.chat.id')]);

                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            $messageService->getInternalErrorMessage()
                        );
                    }

                return response()->json(['status' => 'success']);
            }

            if (str_starts_with(trim($text), '/')) {
                $command = strtolower(explode('@', explode(' ', trim($text))[0])[0]);
                $chatId = (int) $request->input('message.chat.id');

                Log::info('Comando recebido', ['comando' => $command, 'chat_id' => $chatId]);

                if (in_array($command, ['/dia'])) {
                    $summary = $mealReportService->summaryDay($chatId);

                    if(isset($summary['user_calories_goal_kcal']))
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            $messageService->getSummaryWithMacrosMessage($summary, '📅 Resumo de Hoje')
                        );
                    else{
                         $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            $messageService->getSummaryWithoutMacrosMessage($summary, '📅 Resumo de Hoje')
                        );
                    }
                } elseif ($command === '/semana') {
                    $summary = $mealReportService->summaryWeek($chatId);
                    if(isset($summary['user_calories_goal_kcal']))
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            $messageService->getSummaryWithMacrosMessage($summary, '🗓️ Resumo da Semana')
                        );
                    else{
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            $messageService->getSummaryWithoutMacrosMessage($summary, '🗓️ Resumo da Semana')
                        );
                    }
                } elseif ($command === '/excluir') {
                    $deletedMeal = $mealReportService->excluirlastMeal($chatId);
                    if($deletedMeal) {
                        $message = $messageService->defaultResponseMessage($deletedMeal);
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            "🗑️ *Última Refeição Excluída!*\n" .
                            "──────────────────\n" .
                            $message
                        );
                    } else {
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            $messageService->notFoundMealMessage()
                        );
                    }

                } elseif ($command === '/macros'){
                    $telegramService->editMessage(
                        $request->input('message.chat.id'),
                        $chatMessageId,
                        $messageService->getSetMacrosGoalMessage()
                    );
                } elseif ($command === '/app') {
                    $dashboardUrl = URL::temporarySignedRoute('dashboard.login', now()->addMinutes(10), ['chatId' => $chatId]);
                    $telegramService->editMessage(
                        $request->input('message.chat.id'),
                        $chatMessageId,
                        "📈 *Dashboard Anotai*\n" .
                        "──────────────────\n" .
                        "Acompanhe seus macros, refeições e metas pelo painel web:\n" .
                        "[Abrir Dashboard]({$dashboardUrl})"
                    );
                }else {
                    $telegramService->editMessage(
                        $request->input('message.chat.id'),
                        $chatMessageId,
                        $messageService->getStartMessage()
                    );
                }

                return response()->json(['status' => 'success']);
            }

            try{
                    $response = $groqService->textToJson($text);

                    if ($response) {
                        $mealData = $response;

                        Log::info('Análise de refeição concluída', ['itens' => array_column($mealData['items'] ?? [], 'alimento')]);

                        if (!isset($mealData['items']) || !is_array($mealData['items'])) {
                            Log::warning('Formato de resposta inesperado da LLM ao interpretar texto', ['response' => $response]);

                            $telegramService->editMessage(
                                $request->input('message.chat.id'),
                                $chatMessageId,
                                $messageService->getErrorParsingJsonMessage()
                            );
                        } elseif (empty($mealData['items'])) {
                            Log::warning('Nenhum alimento identificado no texto', ['text' => $text]);

                            $telegramService->editMessage(
                                $request->input('message.chat.id'),
                                $chatMessageId,
                                $messageService->getErrorParsingJsonMessage()
                            );
                        }
                        else{

                            $nutritionResponse = $nutritionManagerService->getNutritionInfo($mealData['items']);

                            if (!empty($nutritionResponse['items_nao_identificados'])) {
                                Log::warning("Alimentos não identificados no texto", ['itens' => $nutritionResponse['items_nao_identificados']]);

                                $telegramService->editMessage(
                                    $request->input('message.chat.id'),
                                    $chatMessageId,
                                    $messageService->getErrorParsingJsonMessage()
                                );
                            } else {
                                $telegramService->editMessage(
                                    $request->input('message.chat.id'),
                                    $chatMessageId,
                                    $messageService->defaultResponseMessage($nutritionResponse)
                                );

                                $savedMeal = $mealReportService->save(
                                    $nutritionResponse,
                                    (int) $request->input('message.chat.id'),
                                    $request->input('message.from.username'),
                                    $request->has('update_id') ? (int) $request->input('update_id') : null,
                                    $text
                                );

                                Log::info('Refeição salva com sucesso', ['meal_id' => $savedMeal->id, 'chat_id' => $request->input('message.chat.id'), 'total_calories_kcal' => $nutritionResponse['total_calories_kcal']]);
                            }

                        }
                    } else {
                        $telegramService->editMessage(
                            $request->input('message.chat.id'),
                            $chatMessageId,
                            $messageService->getErrorParsingJsonMessage()
                        );
                    }
            }catch (\Exception $e) {
                Log::error('Erro ao processar a mensagem de texto do Telegram', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

                $telegramService->editMessage(
                    $request->input('message.chat.id'),
                    $chatMessageId,
                    $messageService->getInternalErrorMessage()
                );
            }
        }


        return response()->json([
            'status' => 'success',
        ]);
    }


    private function checkUserMacroRegex($message)
    {
        $patterns = [
            'calories_kcal'    => '/Calorias:\s*\[?\s*(\d+)\s*\]?\s*kcal/iu',
            'carbohydrate_g'   => '/Carboidratos:\s*\[?\s*(\d+)\s*\]?\s*g/iu',
            'protein_g'        => '/Prote[íi]nas:\s*\[?\s*(\d+)\s*\]?\s*g/iu',
            'fat_g'            => '/Gorduras:\s*\[?\s*(\d+)\s*\]?\s*g/iu',
        ];

        $macros = [];

        foreach ($patterns as $key => $value) {
            if (!preg_match($value, $message, $matches)) {
                return false;
            }

            $macros[$key] = (int) $matches[1];
        }

        return $macros;
    }
}
