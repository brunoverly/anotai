<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function getMe()
    {
        return Http::get(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/getMe'
        )->json();
    }

    public function setWebHook(string $url)
    {
        $payload = ['url' => $url];

        $secret = config('services.telegram.webhook_secret');
        if (!empty($secret)) {
            $payload['secret_token'] = $secret;
        }

        return Http::post(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/setWebhook',
            $payload
        )->json();
    }

    public function setMyCommands()
    {
        $payload = ['commands' => [
            ['command' => 'dia', 'description' => 'Resumo do dia'],
            ['command' => 'semana', 'description' => 'Resumo da semana'],
            ['command' => 'excluir', 'description' => 'Exclui a última refeição do dia'],
            ['command' => 'busca', 'description' => 'Busca um alimento no banco pelo nome'],
            ['command' => 'macros', 'description' => 'Define suas metas de macros (calorias, carboidratos, proteínas)'],
            ['command' => 'app', 'description' => 'Link para o app web (dashboard)'],
        ]];

        return Http::post(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/setMyCommands',
            $payload
        )->json();
    }

    public function getFilePath(string $fileId)
    {
        return Http::get(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/getFile',
            [
                'file_id' => $fileId,
            ]
        )->json();
    }

    public function downloadFile($filePath)
    {
        $token = config('services.telegram.bot_token');

        $url = "https://api.telegram.org/file/bot" . $token . "/" . $filePath;

        $response = Http::timeout(30)->get($url);

        if ($response->successful()) {
            return $response->body();
        }

        Log::error('Falha ao baixar arquivo do Telegram', ['status' => $response->status()]);
        return null;
    }

    public function sendMessage(string $chatId, string $text)
    {
        $response = Http::withHeaders([
                'Connection' => 'close'
            ])
            ->post(
                'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage',
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]
            );

        if (!$response->successful()) {
            Log::warning('Telegram recusou o envio da mensagem', ['chat_id' => $chatId, 'status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->json();
    }

    public function editMessage(string $chatId, ?int $messageId, string $text)
    {

        if (!$messageId) {
            return $this->sendMessage($chatId, $text);
        }

        $response = Http::withHeaders([
                'Connection' => 'close'
            ])
            ->post(
                'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/editMessageText',
                [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]
            );

        if (!$response->successful()) {
            Log::warning('Telegram recusou a edição da mensagem', ['chat_id' => $chatId, 'message_id' => $messageId, 'status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->json();
    }

}
