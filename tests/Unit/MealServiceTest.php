<?php

namespace Tests\Unit;

use App\Models\Meal;
use App\Models\User;
use App\Services\MealService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealServiceTest extends TestCase
{
    use RefreshDatabase;

    private function nutritionResult(): array
    {
        return [
            'items' => [
                ['alimento' => 'arroz', 'quantidade' => 100, 'unidade' => 'grama', 'protein_g' => 2.4, 'carbohydrate_g' => 28.2, 'fat_g' => 0.2, 'calories_kcal' => 130],
            ],
            'total_protein_g' => 2.4,
            'total_carbohydrate_g' => 28.2,
            'total_fat_g' => 0.2,
            'total_calories_kcal' => 130,
        ];
    }

    public function test_save_cria_usuario_novo_e_refeicao_quando_nao_existem(): void
    {
        $service = app(MealService::class);

        $meal = $service->save($this->nutritionResult(), 650010743, 'teste', 111, 'comi 100g de arroz');

        $this->assertDatabaseHas('users', ['telegram_chat_id' => 650010743]);
        $this->assertEquals(28.2, $meal->total_carbohydrate_g);
        $this->assertEquals(1, Meal::count());
    }

    public function test_save_com_mesmo_telegram_update_id_atualiza_em_vez_de_duplicar(): void
    {
        $service = app(MealService::class);

        $service->save($this->nutritionResult(), 650010743, 'teste', 111, 'primeira mensagem');
        $service->save($this->nutritionResult(), 650010743, 'teste', 111, 'reenvio do telegram (retry de webhook)');

        $this->assertEquals(1, Meal::count());
    }

    public function test_save_sem_telegram_update_id_sempre_cria_nova_refeicao(): void
    {
        $service = app(MealService::class);

        $service->save($this->nutritionResult(), 650010743, 'teste', null, 'primeira');
        $service->save($this->nutritionResult(), 650010743, 'teste', null, 'segunda');

        $this->assertEquals(2, Meal::count());
    }

    public function test_excluirlastmeal_remove_a_ultima_refeicao_de_hoje(): void
    {
        $service = app(MealService::class);
        $meal = $service->save($this->nutritionResult(), 650010743, 'teste', null, 'comi 100g de arroz');

        $result = $service->excluirlastMeal(650010743);

        $this->assertNotFalse($result);
        $this->assertSoftDeleted('meals', ['id' => $meal->id]);
    }

    public function test_excluirlastmeal_retorna_false_quando_nao_ha_refeicao(): void
    {
        User::factory()->create(['telegram_chat_id' => 650010743]);
        $service = app(MealService::class);

        $result = $service->excluirlastMeal(650010743);

        $this->assertFalse($result);
    }

    public function test_summaryday_sem_usuario_retorna_null(): void
    {
        $service = app(MealService::class);

        $this->assertNull($service->summaryDay(999999999));
    }

    public function test_summaryday_sem_metas_definidas_nao_traz_chaves_de_meta(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => 650010743]);
        app(MealService::class)->save($this->nutritionResult(), 650010743, 'teste', null, 'texto');

        $summary = app(MealService::class)->summaryDay(650010743, $user);

        $this->assertEquals(1, $summary['quantidade_refeicoes']);
        $this->assertArrayNotHasKey('user_calories_goal_kcal', $summary);
    }

    public function test_summaryweek_com_metas_multiplica_meta_diaria_por_7(): void
    {
        $user = User::factory()->create([
            'telegram_chat_id' => 650010743,
            'calories_kcal' => 2000,
            'carbohydrate_g' => 250,
            'protein_g' => 150,
            'fat_g' => 70,
        ]);

        $summary = app(MealService::class)->summaryWeek(650010743, $user);

        $this->assertEquals(14000, $summary['user_calories_goal_kcal']);
        $this->assertEquals(1750, $summary['user_carbohydrate_goal_g']);
    }

    public function test_saveusermacros_atualiza_metas_do_usuario_existente(): void
    {
        User::factory()->create(['telegram_chat_id' => 650010743]);
        $service = app(MealService::class);

        $ok = $service->saveUserMacros(650010743, [
            'calories_kcal' => 2200,
            'carbohydrate_g' => 260,
            'protein_g' => 160,
            'fat_g' => 70,
        ]);

        $this->assertTrue((bool) $ok);
        $this->assertDatabaseHas('users', ['telegram_chat_id' => 650010743, 'calories_kcal' => 2200]);
    }

    public function test_saveusermacros_retorna_false_quando_usuario_nao_existe(): void
    {
        $service = app(MealService::class);

        $result = $service->saveUserMacros(999999999, [
            'calories_kcal' => 2200, 'carbohydrate_g' => 260, 'protein_g' => 160, 'fat_g' => 70,
        ]);

        $this->assertFalse($result);
    }
}
