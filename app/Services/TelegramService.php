<?php

namespace App\Services;
use Illuminate\Support\Facades\Http;

class TelegramService
{
    public function getMe()
    {
        return Http::get(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/getMe'
        )->json();
    }

    public function setWebHook(String $url)
    {
        return Http::post(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/setWebhook',
            [
                'url' => $url,
            ]
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

    public function downloadFile(string $filePath)
    {
        return Http::get(
            'https://api.telegram.org/file/bot' . config('services.telegram.bot_token') . '/' . $filePath
        )->body();
    }

    public function sendMessage(string $chatId, string $text)
    {
        return Http::post(
            'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage',
            [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]
        )->json();
    }
    
}
