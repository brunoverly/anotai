<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OpenFoodService
{

    public function searchInExternalApi(string $food, string $inputUnity)
    {
        try {
            $searchResponse = Http::timeout(15)
                ->get('https://search.openfoodfacts.org/search', [
                    'q'         => $food,
                    'page_size' => 1,
                    'langs'     => 'pt',
                ]);

            $hit = $searchResponse->successful() ? $searchResponse->json('hits.0') : null;

            if (!$hit || empty($hit['code'])) {
                Log::info("Degrau 2: nenhum resultado na busca -> {$food}");
                return null;
            }

            $orignalName = $hit['product_name_pt']
                ?? $hit['product_name']
                ?? $hit['product_name_en']
                ?? $hit['generic_name_pt']
                ?? '';

            $name = (string) Str::of($orignalName)->lower()->ascii()->replace('-', ' ')->squish();
            $searchTerm = (string) Str::of($food)->lower()->replace('-', ' ')->squish();

            if ($name === '' || (!Str::contains($name, $searchTerm) && !Str::contains($searchTerm, $name))) {
                Log::warning("Degrau 2: produto retornado não corresponde ao termo buscado, ignorando", [
                    'termo_buscado' => $food,
                    'produto_encontrado' => $orignalName ?: null,
                ]);

                return null;
            }

            $productResponse = Http::timeout(15)
                ->get("https://world.openfoodfacts.org/api/v2/product/{$hit['code']}.json");

            if (!$productResponse->successful() || $productResponse->json('status') !== 1) {
                Log::warning("Degrau 2: produto {$hit['code']} não encontrado na consulta detalhada");
                return null;
            }

            $product = $productResponse->json('product');
            $nutriments = $product['nutriments'] ?? [];

            $servingSizeG = 1;
            if (!in_array($inputUnity, ['grama', 'ml'])) {
                $servingSizeG = $this->resolveServingSizeG($product, $inputUnity);
                if ($servingSizeG === null) {
                    Log::info("Degrau 2: produto sem peso de embalagem/porção pra converter '{$inputUnity}', deixando cair pro Degrau 3", [
                        'produto' => $product['product_name'] ?? null,
                    ]);
                    return null;
                }
            }

            $protein = $nutriments['proteins_100g'] ?? $nutriments['protein_100g'] ?? null;
            $carb    = $nutriments['carbohydrates_100g'] ?? $nutriments['carbohydrate_100g'] ?? null;
            $fat  = $nutriments['fat_100g'] ?? $nutriments['fats_100g'] ?? 0;
            $calories  = $nutriments['energy-kcal_100g'] ?? $nutriments['energy_kcal_100g'] ?? null;
            $backupCalories = round(((float)($protein ?? 0) * 4) + ((float)($carb ?? 0) * 4) + ((float)$fat * 9), 0);

            $diff = $backupCalories > 0 ? abs(((float)($calories ?? 0)) - $backupCalories) / $backupCalories : 0;
            if ($calories === null || $diff > 0.15) {
                $calories = $backupCalories;
            }

            if ($protein !== null && $carb !== null) {
                Log::info("Degrau 2: Alimento encontrado na API Externa -> {$product['product_name']}", [
                    'protein_g' => $protein,
                    'carbohydrate_g' => $carb,
                    'fat_g' => $fat,
                    'calories_kcal' => $calories,
                ]);

                return [
                    'name'            => NutritionManagerService::sanitizeFoodName(NutritionManagerService::stripBrandFromProductName($product['product_name'] ?? $food, $product['brands'] ?? null)),
                    'protein_g'       => round((float)$protein, 2),
                    'carbohydrate_g'  => round((float)$carb, 2),
                    'fat_g'           => round((float)$fat, 2),
                    'calories_kcal'   => round((float)($calories ?? 0), 0),
                    'serving_size_g'  => $servingSizeG,
                    'source'          => 'openfoodfacts'
                ];
            }
        } catch (\Throwable $e) {
            Log::error("Erro na busca da API Externa: " . $e->getMessage());
        }

        return null;
    }

    private function resolveServingSizeG(array $product, string $inputUnity): ?float
    {
        $fullSize = $this->parseQuantityToGrams($product['product_quantity'] ?? null, $product['product_quantity_unit'] ?? null);
        $servingSize = $this->parseQuantityToGrams($product['serving_quantity'] ?? null, $product['serving_quantity_unit'] ?? null);

        if ($inputUnity === 'unidade') {
            return $fullSize ?? $servingSize;
        }

        return $servingSize ?? $fullSize;
    }

    private function parseQuantityToGrams($quantity, ?string $unity): ?float
    {
        if ($quantity === null || $quantity === '') {
            return null;
        }

        $value = (float) $quantity;

        return match (Str::lower($unity ?? 'g')) {
            'kg', 'l' => $value * 1000,
            'cl' => $value * 10,
            default => $value,
        };
    }
}
