<?php

namespace App\Services;

use App\Models\Food;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class NutritionManagerService
{
    public function __construct(
        private GroqService $groqService,
        private OpenFoodService $openFoodService,
    ) {
    }

    public function getNutritionInfo(array $items)
    {
        $totalProtein = 0;
        $totalCarb = 0;
        $totalFat = 0;
        $totalCalories = 0;
        $totalFoundItems = [];
        $totalNotFoundItems = [];

        foreach ($items as $item) {
            $food = $item['alimento'] ?? '';
            $inputQuantity = (float) ($item['quantidade'] ?? 0);
            $inputUnity = $item['unidade'] ?? 'grama';
            $foodType = $item['tipo'] ?? null;

            $food = $this->searchFood($food, $foodType, $inputUnity);

            if ($food) {
                $totalWeightWeight = $inputQuantity;

                if (!in_array($inputUnity, ['grama', 'ml']) && (float)$food['serving_size_g'] > 0) {
                    $totalWeightWeight = $inputQuantity * (float)$food['serving_size_g'];
                }

                $multiplyerFactor = $totalWeightWeight / 100;

                $prot = round($food['protein_g'] * $multiplyerFactor, 2);
                $carb = round($food['carbohydrate_g'] * $multiplyerFactor, 2);
                $gord = round($food['fat_g'] * $multiplyerFactor, 2);
                $kcal = round($food['calories_kcal'] * $multiplyerFactor, 0);

                $totalProtein += $prot;
                $totalCarb += $carb;
                $totalFat += $gord;
                $totalCalories += $kcal;

                $totalFoundItems[] = [
                    'alimento' => $food['name'],
                    'quantidade' => $inputQuantity,
                    'unidade' => $inputUnity,
                    'protein_g' => $prot,
                    'carbohydrate_g' => $carb,
                    'fat_g' => $gord,
                    'calories_kcal' => $kcal
                ];
            } else {
                Log::warning("Alimento não identificado em nenhum degrau -> {$food}");
                $totalNotFoundItems[] = $food;
            }
        }

        return [
            'items' => $totalFoundItems,
            'items_nao_identificados' => $totalNotFoundItems,
            'total_protein_g' => round($totalProtein, 2),
            'total_carbohydrate_g' => round($totalCarb, 2),
            'total_fat_g' => round($totalFat, 2),
            'total_calories_kcal' => $totalCalories
        ];
    }

    private function searchFood(string $food, ?string $foodType, string $inputUnity)
    {
        $food = self::sanitizeFoodName($food);

        $localFood = $this->searchInLocalDatabase($food);
        if ($localFood) {
            Log::info("Degrau 1: Alimento encontrado no banco local -> {$localFood->name}");

            $foodArray = $localFood->toArray();
            $foodArray['source'] = 'local'; // Tag para identificação no cálculo

            return $foodArray;
        }

        if ($foodType === 'in_natura') {
            Log::info("Degrau 2: pulado (alimento in natura) -> {$food}");
        } else {
            Log::info("Degrau 2: Buscando na API Externa -> {$food}");
            $apiFood = $this->openFoodService->searchInExternalApi($food, $inputUnity);
            if ($apiFood) {
                $newFood = Food::updateOrCreate(['name' => $apiFood['name']], $apiFood);

                return $newFood->toArray();
            }
        }

        Log::info("Degrau 3: API falhou. Solicitando estimativa da LLM -> {$food}");
        $llmFood = $this->groqService->getNutricionInfo($food, $inputUnity);
        if ($llmFood) {
            $newFood = Food::updateOrCreate(['name' => $llmFood['name']], $llmFood);

            return $newFood->toArray();
        }

        return null;
    }

    public static function sanitizeFoodName(string $nome): string
    {
        return (string) Str::of($nome)->lower()->ascii()->squish();
    }

    public static function stripBrandFromProductName(string $productName, ?string $brands): string
    {
        $sanitizedName = $productName;

        if (!empty($brands)) {
            foreach (explode(',', $brands) as $brand) {
                $brand = trim($brand);
                if ($brand === '') {
                    continue;
                }
                $sanitizedName = preg_replace('/\b' . preg_quote($brand, '/') . '\b/ui', '', $sanitizedName) ?? $sanitizedName;
            }
        }

        $sanitizedName = preg_replace('/\b\d+[.,]?\d*\s?(l|litros?|ml|kg|g|gr|gramas?|un|unidades?)\b/ui', '', $sanitizedName) ?? $sanitizedName;

        $sanitizedName = trim(preg_replace('/\s+/', ' ', $sanitizedName) ?? $sanitizedName, " -,");

        $tooShort = $sanitizedName === '' || mb_strlen($sanitizedName) < mb_strlen($productName) * 0.5;
        $badPrefix = $sanitizedName !== '' && preg_match('/^[\d\W]/u', $sanitizedName) === 1;

        if ($tooShort || $badPrefix) {
            return $productName;
        }

        return $sanitizedName;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function searchInLocalDatabase(string $food)
    {
        $exact = Food::where('name', $food)->first();
        if ($exact) {
            return $exact;
        }

        $terms = [$food];
        if (Str::endsWith($food, 's')) {
            $terms[] = Str::substr($food, 0, -1);
        }

        $narrowQuery = Food::query();
        foreach ($terms as $term) {
            $narrowQuery->orWhereRaw('? LIKE \'%\' || name || \'%\'', [$term]);
        }
        $specified = $narrowQuery->orderByRaw('LENGTH(name) DESC')->first();
        if ($specified) {
            return $specified;
        }

        $wideQuery = Food::query();
        foreach ($terms as $term) {
            $wideQuery->orWhere('name', 'like', '%' . $this->escapeLike($term) . '%');
        }

        return $wideQuery->orderByRaw('LENGTH(name) ASC')->first();
    }
}
