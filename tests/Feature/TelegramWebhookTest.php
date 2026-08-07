<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\Meal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.telegram.webhook_secret' => 'test-secret']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);
    }

    private function payload(array $message, ?int $updateId = 123): array
    {
        $update = ['message' => $message];
        if ($updateId !== null) {
            $update['update_id'] = $updateId;
        }

        return $update;
    }

    private function postWebhook(array $payload)
    {
        return $this->postJson('/api/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);
    }

    public function test_secret_invalido_e_recusado_com_403(): void
    {
        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 1,
            'message' => ['chat' => ['id' => 650010743], 'text' => 'oi'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'secret-errado']);

        $response->assertStatus(403);
        $this->assertDatabaseCount('meals', 0);
    }

    public function test_mensagem_de_texto_com_unidade_nao_grama_salva_macros_corretos(): void
    {
        // Regressão do bug em que o pipeline lia $item['quantity']/$item['unity'] (inglês)
        // em vez de $item['quantidade']/$item['unidade'] (chaves reais devolvidas pela LLM),
        // zerando os macros de qualquer refeição medida em unidade não-grama.
        Food::create([
            'name' => 'pao de forma',
            'protein_g' => 8,
            'carbohydrate_g' => 40,
            'fat_g' => 1,
            'calories_kcal' => 190,
            'serving_size_g' => 31,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
            'api.groq.com/*' => Http::response(['choices' => [
                ['message' => ['content' => json_encode([
                    'items' => [
                        ['alimento' => 'pão de forma', 'quantidade' => 2, 'unidade' => 'fatia', 'tipo' => 'in_natura'],
                    ],
                ])]],
            ]], 200),
        ]);

        $response = $this->postWebhook($this->payload([
            'message_id' => 1,
            'chat' => ['id' => 650010743],
            'from' => ['username' => 'teste'],
            'text' => 'comi 2 fatias de pão de forma',
        ]));

        $response->assertStatus(200);

        $meal = Meal::first();
        $this->assertNotNull($meal);
        $this->assertEquals(round(40 * 0.62, 2), $meal->total_carbohydrate_g);
        $this->assertNotEquals(0, $meal->total_carbohydrate_g);
    }

    public function test_mensagem_sem_alimento_reconhecivel_nao_salva_refeicao(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
            'api.groq.com/*' => Http::response(['choices' => [
                ['message' => ['content' => json_encode(['items' => []])]],
            ]], 200),
        ]);

        $response = $this->postWebhook($this->payload([
            'message_id' => 1,
            'chat' => ['id' => 650010743],
            'from' => ['username' => 'teste'],
            'text' => 'bom dia',
        ]));

        $response->assertStatus(200);
        $this->assertDatabaseCount('meals', 0);
    }

    public function test_update_id_repetido_nao_duplica_refeicao_em_retry_de_webhook(): void
    {
        Food::create([
            'name' => 'arroz',
            'protein_g' => 2.4,
            'carbohydrate_g' => 28.2,
            'fat_g' => 0.2,
            'calories_kcal' => 130,
            'serving_size_g' => 1,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
            'api.groq.com/*' => Http::response(['choices' => [
                ['message' => ['content' => json_encode([
                    'items' => [['alimento' => 'arroz', 'quantidade' => 100, 'unidade' => 'grama', 'tipo' => 'in_natura']],
                ])]],
            ]], 200),
        ]);

        $message = [
            'message_id' => 1,
            'chat' => ['id' => 650010743],
            'from' => ['username' => 'teste'],
            'text' => 'comi 100g de arroz',
        ];

        $this->postWebhook($this->payload($message, 999));
        $this->postWebhook($this->payload($message, 999));

        $this->assertDatabaseCount('meals', 1);
    }

    public function test_comando_dia_nao_quebra_quando_usuario_ainda_nao_existe(): void
    {
        $response = $this->postWebhook($this->payload([
            'message_id' => 1,
            'chat' => ['id' => 650010743],
            'from' => ['username' => 'teste'],
            'text' => '/dia',
        ]));

        $response->assertStatus(200);
    }

    public function test_comando_busca_retorna_alimentos_encontrados(): void
    {
        Food::factory()->create(['name' => 'frango grelhado']);
        Food::factory()->create(['name' => 'frango a passarinho']);
        Food::factory()->create(['name' => 'arroz branco']);

        $response = $this->postWebhook($this->payload([
            'message_id' => 1,
            'chat' => ['id' => 650010743],
            'from' => ['username' => 'teste'],
            'text' => '/busca frango',
        ]));

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'editMessageText')
                && str_contains($request['text'], 'Frango Grelhado')
                && str_contains($request['text'], 'Frango A Passarinho')
                && !str_contains($request['text'], 'Arroz Branco');
        });
    }

    public function test_comando_busca_sem_resultado_avisa_que_nao_esta_cadastrado(): void
    {
        $response = $this->postWebhook($this->payload([
            'message_id' => 1,
            'chat' => ['id' => 650010743],
            'from' => ['username' => 'teste'],
            'text' => '/busca alimento inexistente xyz',
        ]));

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'editMessageText')
                && str_contains($request['text'], 'não está cadastrado');
        });
    }

    public function test_comando_busca_sem_termo_pede_o_nome_do_alimento(): void
    {
        $response = $this->postWebhook($this->payload([
            'message_id' => 1,
            'chat' => ['id' => 650010743],
            'from' => ['username' => 'teste'],
            'text' => '/busca',
        ]));

        $response->assertStatus(200);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'editMessageText')
                && str_contains($request['text'], 'nome do alimento');
        });
    }
}
