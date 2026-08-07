<?php
namespace App\Services;

class MessageService
{
    private const ABBREVIATED_UNITS = [
        'grama' => 'g',
        'ml' => 'ml',
        'unidade' => 'un',
        'fatia' => 'fatia',
        'colher' => 'colher',
        'dose' => 'dose',
    ];

    public function getStartMessage(){
        return "🤖 *Olá!*\n" .
            "──────────────────\n" .
            "Sou o Anotai, seu assistente de nutrição. Posso te ajudar a registrar suas refeições e acompanhar suas metas de macros.\n\n" .
            "📌 *Como me usar:*\n" .
            "1️⃣ Envie uma mensagem de voz descrevendo o que você comeu (ex: \"comi 150g de arroz, 200g de frango e 1 colher de azeite\").\n" .
            "2️⃣ Ou digite uma mensagem de texto descrevendo sua refeição.\n" .
            "3️⃣ Use os comandos /dia, /semana, /macros, /busca ou /app para ver seus registros e metas.\n\n" .
            "💡 Dica: Quanto mais detalhado você for na descrição da refeição, melhor será a análise nutricional.";
    }

    public function getAudioReceivedMessage(){
        return  "🎤 *Áudio Recebido!*\n" .
                "⏳ _Processando sua mensagem de voz..._\n" .
                "🤖 _Aguarde um momento enquanto a mágica acontece._";
    }

    public function getTextReceivedMessage(){
        return  "✉️ *Mensagem Recebida!*\n" .
                "⏳ _Processando sua mensagem de texto..._\n" .
                "🤖 _Aguarde um momento enquanto a mágica acontece._";
    }

    public function getErrorParsingJsonMessage(){
        return  "😬 *Ops!*\n" .
                "Não consegui identificar um dos alimentos na sua mensagem. Pode repetir de uma forma mais detalhada, por favor?";
    }

    public function getInternalErrorMessage(){
        return  "😬 *Ops!*\n" .
                "Ocorreu um erro interno ao processar sua mensagem. Por favor, tente novamente mais tarde.";
    }

    public function defaultResponseMessage(array $mealData)
    {
        $message = "🍽️ *Resumo da Refeição*\n\n";

        foreach ($mealData['items'] as $item) {
            $unity = self::ABBREVIATED_UNITS[$item['unidade']] ?? $item['unidade'];
            $message .= "• " . ucwords($item['alimento']) . " — " . $item['quantidade'] . " " . $unity . " · " . $item['calories_kcal'] . " kcal\n";
        }

        $message .= "\n───────────────────\n";
        $message .= "🔥 *Total:* " . $mealData['total_calories_kcal'] . " kcal\n";
        $message .= "🥩 Proteína: " . $mealData['total_protein_g'] . "g \n";
        $message .= "🍞 Carbo: " . $mealData['total_carbohydrate_g'] . "g \n";
        $message .= "🥑 Gordura: " . $mealData['total_fat_g'] . "g";

        return $message;
    }

    public function getMacrosUpdateMessage(array $macros){
        return "✅ *Metas de Macros Atualizadas!*\n" .
                "──────────────────\n" .
                "Suas metas foram atualizadas com sucesso:\n" .
                "🔥 Calorias: {$macros['calories_kcal']} kcal\n" .
                "🍞 Carboidratos: {$macros['carbohydrate_g']} g\n" .
                "🥩 Proteínas: {$macros['protein_g']} g\n" .
                "🥑 Gorduras: {$macros['fat_g']} g \n\n".
                "Agora os comandos /hoje e /semana mostrarão seu progresso.";
    }

    public function getSummaryWithoutMacrosMessage(?array $summary, string $title): string
    {
        if (!$summary || $summary['quantidade_refeicoes'] === 0) {
            $period = $summary['period_formatado'] ?? null;
            $titleWithPeriod = $period ? "{$title} ({$period})" : $title;

            return "{$titleWithPeriod}\n──────────────────\nNenhuma refeição registrada ainda nesse período.";
        }

        $message = "{$title} ({$summary['period_formatado']})\n──────────────────\n";
        $message .= "🍽️ {$summary['quantidade_refeicoes']} refeição(ões) registrada(s)\n\n";
        $message .= "🔥 *Total:* {$summary['total_calories_kcal']} kcal\n";
        $message .= "🥩 Proteína: {$summary['total_protein_g']}g   🍞 Carbo: {$summary['total_carbohydrate_g']}g   🥑 Gordura: {$summary['total_fat_g']}g";

        return $message;
    }

    public function getSummaryWithMacrosMessage(array $summary, string $titleWithPeriod): string
    {
        if ($summary['quantidade_refeicoes'] === 0) {
            return "*{$titleWithPeriod}* ({$summary['period_formatado']})\n──────────────────\nNenhuma refeição registrada ainda nesse período.";
        }
        $message = "*{$titleWithPeriod}* ({$summary['period_formatado']})\n──────────────────\n\n";

        $status = [];

        $message .= $this->macroArray('🔥', 'Calorias', (float)$summary['total_calories_kcal'], (float)$summary['user_calories_goal_kcal'], 'kcal', true, $status);
        $message .= $this->macroArray('🥩', 'Proteínas', (float)$summary['total_protein_g'], (float)$summary['user_protein_goal_g'], 'g', false, $status);
        $message .= $this->macroArray('🍞', 'Carboidratos', (float)$summary['total_carbohydrate_g'], (float)$summary['user_carbohydrate_goal_g'], 'g', true, $status);
        $message .= $this->macroArray('🥑', 'Gorduras', (float)$summary['total_fat_g'], (float)$summary['user_fat_goal_g'], 'g', true, $status);

        if (!empty($status)) {
            $message .= "📌 *Situação*\n" . implode("\n", $status);
        }

        return rtrim($message);
    }

    private function macroArray(string $emoji,
                                string $label,
                                float $consumed,
                                float $goal,
                                string $unity,
                                bool $alertOnExceed,
                                array &$status): string
    {
        $percentage = $goal > 0 ? ($consumed / $goal) * 100 : 0;
        $filledBlocks = min(10, (int) round($percentage / 10));
        $progresBar = str_repeat('●', $filledBlocks) . str_repeat('○', 10 - $filledBlocks);

        $tag = '';
        $diff = '';
        if ($percentage > 100) {
            $statusEmoji = $alertOnExceed ? '🔴' : '🟢';
            $tag = " {$statusEmoji}";
            $diff = ' _(+' . round($consumed - $goal) . " {$unity})_";

            if ($alertOnExceed) {
                $status[] = "• 🔴 {$label} acima da meta.";
            } else {
                $status[] = "• 🟢 Meta de {$label} atingida!";
            }
        }

        return "{$emoji} *{$label}*\n" .
            " {$consumed} / " . round($goal) . " {$unity}{$tag}{$diff}\n" .
            " {$progresBar} " . round($percentage) . "%\n\n";
    }

    public function notFoundMealMessage(){
        return  "⚠️ *Nenhuma Refeição Encontrada!*\n" .
                    "──────────────────\n" .
                    "Não foi encontrada uma refeição registrada hoje para excluir.";
    }

    public function getFoodSearchMessage(string $termo, \Illuminate\Support\Collection $alimentos): string
    {
        if ($alimentos->isEmpty()) {
            return "🔎 *Busca de Alimento*\n" .
                "──────────────────\n" .
                "Nenhum alimento com \"{$termo}\" ainda não está cadastrado no banco.";
        }

        $lista = $alimentos->map(fn ($nome) => "• " . ucwords($nome))->implode("\n");

        return "🔎 *Busca de Alimento*\n" .
            "──────────────────\n" .
            "Encontrei " . $alimentos->count() . " resultado(s) para \"{$termo}\":\n\n" .
            $lista;
    }

    public function getSetMacrosGoalMessage(){
        return "🎯  *Metas de Macros*\n" .
                    "──────────────────\n" .
                    "Copie esta mensagem e preencha dentro das \\[ \\] os valores das suas metas:\n" .
                    "*somente os valores numéricos, sem virgulas ou pontos.*\n" .
                    "🔥 Calorias: \\[   \\] kcal\n" .
                    "🍞 Carboidratos: \\[   \\] g\n" .
                    "🥩 Proteínas: \\[   \\] g\n" .
                    "🥑 Gorduras: \\[   \\] g";
    }
}
