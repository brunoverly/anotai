<?php

namespace Tests\Unit;

use App\Services\OpenFoodService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenFoodServiceTest extends TestCase
{
    public function test_produto_com_hifen_no_termo_buscado_e_espaco_no_nome_retornado_e_aceito(): void
    {
        // Regressão: a busca por "coca-cola" batia contra o produto real "Coca Cola LT 350ml"
        // e era rejeitada porque o hífen do termo buscado não batia com o espaço do nome do
        // produto na comparação Str::contains, fazendo o Degrau 2 falhar sempre para esse caso.
        Http::fake([
            'search.openfoodfacts.org/*' => Http::response([
                'hits' => [
                    ['code' => '123456', 'product_name_pt' => 'Coca Cola LT 350ml'],
                ],
            ], 200),
            'world.openfoodfacts.org/*' => Http::response([
                'status' => 1,
                'product' => [
                    'product_name' => 'Coca Cola LT 350ml',
                    'brands' => 'Coca-Cola',
                    'nutriments' => [
                        'proteins_100g' => 0,
                        'carbohydrates_100g' => 10.6,
                        'fat_100g' => 0,
                        'energy-kcal_100g' => 42,
                    ],
                ],
            ], 200),
        ]);

        $service = new OpenFoodService();

        $result = $service->searchInExternalApi('coca-cola', 'grama');

        $this->assertNotNull($result);
        $this->assertEquals(10.6, $result['carbohydrate_g']);
    }

    public function test_produto_realmente_diferente_continua_sendo_rejeitado(): void
    {
        Http::fake([
            'search.openfoodfacts.org/*' => Http::response([
                'hits' => [
                    ['code' => '999999', 'product_name_pt' => 'Suco de Uva Integral'],
                ],
            ], 200),
        ]);

        $service = new OpenFoodService();

        $result = $service->searchInExternalApi('coca-cola', 'grama');

        $this->assertNull($result);
    }
}
