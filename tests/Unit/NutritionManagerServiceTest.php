<?php

namespace Tests\Unit;

use App\Models\Food;
use App\Services\NutritionManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NutritionManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_por_grama_calcula_macros_proporcionais(): void
    {
        Food::create([
            'name' => 'arroz',
            'protein_g' => 2.4,
            'carbohydrate_g' => 28.2,
            'fat_g' => 0.2,
            'calories_kcal' => 130,
            'serving_size_g' => 1,
        ]);

        $service = app(NutritionManagerService::class);

        $result = $service->getNutritionInfo([
            ['alimento' => 'arroz', 'quantidade' => 100, 'unidade' => 'grama', 'tipo' => 'in_natura'],
        ]);

        $this->assertCount(1, $result['items']);
        $this->assertEmpty($result['items_nao_identificados']);
        $this->assertEquals(28.2, $result['total_carbohydrate_g']);
        $this->assertEquals(2.4, $result['total_protein_g']);
        $this->assertEquals(130, $result['total_calories_kcal']);
    }

    public function test_resolve_por_unidade_nao_grama_converte_pelo_serving_size(): void
    {
        // Regressão do bug: quantidade em unidade não-grama precisa ser multiplicada
        // pelo serving_size_g do alimento antes de calcular a proporção por 100g.
        Food::create([
            'name' => 'pao de forma',
            'protein_g' => 8,
            'carbohydrate_g' => 40,
            'fat_g' => 1,
            'calories_kcal' => 190,
            'serving_size_g' => 31,
        ]);

        $service = app(NutritionManagerService::class);

        $result = $service->getNutritionInfo([
            ['alimento' => 'pão de forma', 'quantidade' => 2, 'unidade' => 'fatia', 'tipo' => 'in_natura'],
        ]);

        // 2 fatias * 31g = 62g -> 62/100 = 0.62 de fator
        $this->assertEquals(round(40 * 0.62, 2), $result['total_carbohydrate_g']);
        $this->assertEquals(round(8 * 0.62, 2), $result['total_protein_g']);
    }

    public function test_alimento_nao_encontrado_em_nenhum_degrau_cai_em_nao_identificados(): void
    {
        Http::fake([
            'search.openfoodfacts.org/*' => Http::response(['hits' => []], 200),
            'api.groq.com/*' => Http::response(['choices' => [
                ['message' => ['content' => '{}']],
            ]], 200),
        ]);

        $service = app(NutritionManagerService::class);

        $result = $service->getNutritionInfo([
            ['alimento' => 'alimento inexistente xyz', 'quantidade' => 1, 'unidade' => 'unidade', 'tipo' => 'industrializado'],
        ]);

        $this->assertEmpty($result['items']);
        $this->assertCount(1, $result['items_nao_identificados']);
    }

    public function test_alimento_tipo_in_natura_pula_degrau_2_e_vai_direto_para_llm(): void
    {
        Http::fake([
            'search.openfoodfacts.org/*' => Http::response(['hits' => []], 200),
            'api.groq.com/*' => Http::response(['choices' => [
                ['message' => ['content' => json_encode([
                    'name' => 'manga',
                    'protein_g' => 0.8,
                    'carbohydrate_g' => 15.0,
                    'fat_g' => 0.4,
                    'calories_kcal' => 65,
                    'peso_unidade_g' => 200,
                ])]],
            ]], 200),
        ]);

        $service = app(NutritionManagerService::class);

        $result = $service->getNutritionInfo([
            ['alimento' => 'manga', 'quantidade' => 1, 'unidade' => 'unidade', 'tipo' => 'in_natura'],
        ]);

        // A API externa (Open Food Facts) não deve ter sido chamada para item in_natura.
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'openfoodfacts.org');
        });

        $this->assertCount(1, $result['items']);
        $this->assertEquals('manga', Food::first()->name);
        $this->assertEquals(15.0, Food::first()->carbohydrate_g);
    }
}
