<?php

namespace App\Http\Controllers;

use App\Services\TranscriptionService;
use App\Services\MealParserService;
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
        MealParserService $mealParserService)
    {

        Log::info($request->all());

        if ($request->has('message.voice')) {
            log::info('Mensagem de voz recebida', ['message' => $request->input('message')]);

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

                        $telegramService->sendMessage(
                            $request->input('message.chat.id'),
                            "✅ *Transcrição e Análise Concluídas!*\n" .
                            "──────────────────\n" .
                            "📝 _Transcrição:_\n" .
                            "`{$transcribedText}`\n\n" .
                            "🍽️ _Alimentos e Quantidades:_\n" .
                            "```json\n" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n```"
                        );
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

        return response()->json([
            'status' => 'success',
        ]);
    }
}
}
