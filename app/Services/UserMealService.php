<?php

namespace App\Services;

use App\Models\Meal;
use App\Models\User;

class UserMealService
{
    public function save(array $nutritionResult, int $chatId, ?string $username, ?int $telegramUpdateId, ?string $rawText): Meal
    {
        $user = User::firstOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'name' => $username ?? "telegram_{$chatId}",
                'telegram_username' => $username,
            ]
        );

        $dados = [
            'raw_text' => $rawText,
            'items' => $nutritionResult['items'],
            'total_protein_g' => $nutritionResult['total_protein_g'],
            'total_carbohydrate_g' => $nutritionResult['total_carbohydrate_g'],
            'total_fat_g' => $nutritionResult['total_fat_g'],
            'total_calories_kcal' => $nutritionResult['total_calories_kcal'],
            'consumed_at' => now(),
        ];

        // Sem update_id não há como deduplicar; grava direto.
        if ($telegramUpdateId === null) {
            return Meal::create($dados + ['user_id' => $user->id, 'telegram_update_id' => null]);
        }

        // updateOrCreate pelo par (user_id, telegram_update_id) evita duplicar a
        // refeição se o Telegram reentregar o mesmo webhook (retry por timeout).
        return Meal::updateOrCreate(
            ['user_id' => $user->id, 'telegram_update_id' => $telegramUpdateId],
            $dados
        );
    }
}
