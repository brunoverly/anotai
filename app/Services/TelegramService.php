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
        // Log::info('https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/getFile', ['file_id' => $fileId]);
        return Http::get(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/getFile',
            [
                'file_id' => $fileId,
            ]
        )->json();
    }

    public function downloadFile($filePath)
{
    // O formato correto para download de arquivos do Telegram é:
    // https://api.telegram.org/file/bot<TOKEN>/<FILE_PATH>

    // Garanta que o seu token NÃO está duplicando a palavra 'bot'
    $token = config('services.telegram.bot_token'); // Ex: "8878774032:AAGPNX..."

    // Se o seu token no .env NÃO começar com 'bot', concatenamos aqui de forma limpa:
    $url = "https://api.telegram.org/file/bot" . $token . "/" . $filePath;

    Log::info('Tentando baixar arquivo do Telegram', ['url' => $url]);

    $response = Http::timeout(30)->get($url);

    if ($response->successful()) {
        return $response->body();
    }

    Log::error('Falha ao baixar arquivo do Telegram', ['status' => $response->status()]);
    return null;
}

    public function sendMessage(string $chatId, string $text)
    {
        return Http::withHeaders([
                'Connection' => 'close' // 👈 Evita o cURL error 35 / Connection reset
            ])
            ->post(
                'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage',
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]
            )->json();
    }

    public function editMessage(string $chatId, ?int $messageId, string $text)
    {
        // Sem message_id (ex: falha ao enviar a mensagem de "processando" original),
        // não tem o que editar — cai pra mandar uma mensagem nova em vez de quebrar.
        if (!$messageId) {
            return $this->sendMessage($chatId, $text);
        }

        return Http::withHeaders([
                'Connection' => 'close' // 👈 Evita o cURL error 35 / Connection reset
            ])
            ->post(
                'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/editMessageText',
                [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]
            )->json();
    }

}
