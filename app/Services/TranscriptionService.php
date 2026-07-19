<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    public function transcribe(string $relativeFilePath)
    {
        log::info('Iniciando transcrição do arquivo de áudio', ['file_path' => $relativeFilePath]);

        $apiKey = config('services.groq.api_key');
        $absolutePath = Storage::path($relativeFilePath);
        
        log::info('Caminho absoluto do arquivo', ['absolute_path' => $absolutePath]);
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
}
