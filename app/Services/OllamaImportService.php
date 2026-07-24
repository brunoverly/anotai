<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaImportService
{
    private const ARQUIVO_ORIGEM = 'storage/tbca/alimentos.txt';
    private const ARQUIVO_DESTINO = 'storage/tbca/alimentos.json';

    // Só controla de quanto em quanto tempo grava progresso em disco — não é
    // mais o tamanho do lote mandado pro Ollama. Testamos mandar vários itens
    // numa chamada só (Llama 3.1, Qwen3, Qwen3.5) e nenhum modelo local deu
    // conta de devolver mais de 1 item por vez de forma confiável, então cada
    // chamada ao Ollama agora processa exatamente 1 alimento.
    private const TAMANHO_LOTE = 50;

    private const OLLAMA_URL = 'http://localhost:11434/api/chat';

    // qwen3:8b com "think: false" — validado em 10 iterações de prompt:
    // ~0,7-1s por item, sem alucinação, sem vazamento de marca, respeitando
    // tipo/variedade do alimento. Com thinking ligado a qualidade também é
    // boa, mas 20-35s/item é inviável pros 5.668 itens da TBCA.
    private const OLLAMA_MODEL = 'qwen3:8b';

    private string $caminhoOrigem;
    private string $caminhoDestino;

    public function __construct()
    {
        $this->caminhoOrigem = base_path(self::ARQUIVO_ORIGEM);
        $this->caminhoDestino = base_path(self::ARQUIVO_DESTINO);
    }

    /**
     * Importa o arquivo NDJSON da TBCA, lote por lote: extrai os macros de
     * forma determinística (sem LLM, é só parsing) e pede pro Ollama somente
     * o "nome curto" pesquisável de cada descrição. Grava incrementalmente
     * no destino a cada lote — se o processo cair no meio, roda de novo e
     * ele pula os códigos já processados em vez de recomeçar do zero.
     */
    public function importar(): void
    {
        $jaProcessados = $this->carregarCodigosJaProcessados();

        $handle = fopen($this->caminhoOrigem, 'r');
        if (!$handle) {
            throw new \RuntimeException("Não consegui abrir {$this->caminhoOrigem}");
        }

        $lote = [];
        $totalProcessado = count($jaProcessados);
        $totalPulado = 0;
        $totalSemEnergia = 0;

        while (($linha = fgets($handle)) !== false) {
            $item = json_decode($linha, true);
            if (!$item || empty($item['codigo'])) {
                continue;
            }

            if (isset($jaProcessados[$item['codigo']])) {
                $totalPulado++;
                continue;
            }

            $macros = $this->extrairMacros($item);
            if ($macros === null) {
                // Sem energia (kcal) utilizável nessa entrada — não dá pra
                // aproveitar como alimento, pula sem gastar chamada de LLM.
                $totalSemEnergia++;
                continue;
            }

            $lote[] = [
                'codigo' => $item['codigo'],
                'descricao' => rtrim(trim($item['descricao']), ','),
                'macros' => $macros,
            ];

            if (count($lote) >= self::TAMANHO_LOTE) {
                $this->processarLote($lote);
                $totalProcessado += count($lote);
                $this->log("Processados: {$totalProcessado} | pulados (já feitos): {$totalPulado} | sem energia: {$totalSemEnergia}");
                $lote = [];
            }
        }

        if (!empty($lote)) {
            $this->processarLote($lote);
            $totalProcessado += count($lote);
            $this->log("Processados: {$totalProcessado} | pulados (já feitos): {$totalPulado} | sem energia: {$totalSemEnergia}");
        }

        fclose($handle);

        $this->log("Importação concluída. Total no arquivo de destino: {$totalProcessado}");
    }

    /**
     * Roda depois de `importar()`: encontra itens cujo "name" colidiu com o
     * de outro item (mesmo nome, alimentos diferentes) e regenera só esses,
     * usando o prompt atual — mais barato que reprocessar tudo de novo.
     */
    public function reprocessarDuplicados(): void
    {
        $dados = json_decode(file_get_contents($this->caminhoDestino), true) ?? [];

        $contagem = array_count_values(array_map(
            fn ($item) => mb_strtolower($item['name']),
            $dados
        ));

        $codigosDuplicados = [];
        foreach ($dados as $item) {
            if ($contagem[mb_strtolower($item['name'])] > 1) {
                $codigosDuplicados[$item['codigo']] = true;
            }
        }

        $this->log('Códigos com nome duplicado a reprocessar: ' . count($codigosDuplicados));

        if (empty($codigosDuplicados)) {
            return;
        }

        // Descrição original só existe no arquivo NDJSON de origem, não no
        // destino (que só guarda o nome já limpo) — precisa reler do início.
        $descricoesPorCodigo = [];
        $handle = fopen($this->caminhoOrigem, 'r');
        while (($linha = fgets($handle)) !== false) {
            $item = json_decode($linha, true);
            if ($item && isset($codigosDuplicados[$item['codigo'] ?? null])) {
                $descricoesPorCodigo[$item['codigo']] = rtrim(trim($item['descricao']), ',');
            }
        }
        fclose($handle);

        $total = 0;
        foreach ($dados as &$item) {
            if (!isset($codigosDuplicados[$item['codigo']])) {
                continue;
            }

            $descricao = $descricoesPorCodigo[$item['codigo']] ?? null;
            if ($descricao === null) {
                continue;
            }

            $nomeCurto = $this->pedirNomeCurto($descricao);
            if ($nomeCurto !== null) {
                $item['name'] = ucfirst($nomeCurto);
            }

            $total++;
            if ($total % 50 === 0) {
                file_put_contents($this->caminhoDestino, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $this->log("Reprocessados: {$total} de " . count($codigosDuplicados));
            }
        }
        unset($item);

        file_put_contents($this->caminhoDestino, json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->log("Reprocessamento de duplicados concluído. Total: {$total}");
    }

    /**
     * Extrai calorias/proteína/carboidrato/gordura por 100g do array
     * "nutrientes" da TBCA — parsing puro, sem LLM nenhuma envolvida aqui.
     * Números vêm em formato pt-BR (vírgula decimal) e alguns valores são
     * "NA" (não se aplica) ou "tr" (traço, quantidade insignificante) em vez
     * de número.
     */
    private function extrairMacros(array $item): ?array
    {
        $valores = [];
        foreach ($item['nutrientes'] ?? [] as $nutriente) {
            $chave = ($nutriente['Componente'] ?? '') . '|' . ($nutriente['Unidades'] ?? '');
            $valores[$chave] = $nutriente['Valor por 100g'] ?? null;
        }

        $calorias = $this->paraNumero($valores['Energia|kcal'] ?? null);
        $proteina = $this->paraNumero($valores['Proteína|g'] ?? null) ?? 0.0;
        $gordura = $this->paraNumero($valores['Lipídios|g'] ?? null) ?? 0.0;
        $carboidrato = $this->paraNumero($valores['Carboidrato disponível|g'] ?? null)
            ?? $this->paraNumero($valores['Carboidrato total|g'] ?? null)
            ?? 0.0;

        if ($calorias === null) {
            return null;
        }

        // Mesma checagem de sanidade que já aplicamos nas estimativas de API/LLM
        // do NutritionManagerService: se a caloria declarada divergir demais
        // (>15%) do que os próprios macros implicam (4/4/9), prioriza a conta
        // em vez do valor bruto da fonte — a TBCA é confiável, mas isso é uma
        // rede de segurança barata contra erro de transcrição na tabela.
        $caloriaCalculada = round(($proteina * 4) + ($carboidrato * 4) + ($gordura * 9), 0);
        $divergencia = $caloriaCalculada > 0 ? abs($calorias - $caloriaCalculada) / $caloriaCalculada : 0;
        if ($divergencia > 0.15) {
            $calorias = $caloriaCalculada;
        }

        return [
            'calories_kcal' => $calorias,
            'protein_g' => $proteina,
            'carbohydrate_g' => $carboidrato,
            'fat_g' => $gordura,
        ];
    }

    private function paraNumero(?string $valor): ?float
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);
        if ($valor === '' || strtolower($valor) === 'na' || strtolower($valor) === 'tr') {
            return null;
        }

        return (float) str_replace(',', '.', $valor);
    }

    /**
     * Processa o lote item a item (cada um é uma chamada separada ao Ollama
     * — ver comentário do TAMANHO_LOTE), monta o resultado final e grava.
     */
    private function processarLote(array $lote): void
    {
        $resultados = [];
        foreach ($lote as $item) {
            $nomeCurto = $this->pedirNomeCurto($item['descricao']);

            $resultados[] = [
                'codigo' => $item['codigo'],
                // Se a LLM falhar, cai pra descrição original em vez de
                // perder o item inteiro — dá pra rodar uma limpeza extra
                // nesses casos depois, se precisar.
                'name' => $nomeCurto !== null ? ucfirst($nomeCurto) : $item['descricao'],
                'calories_kcal' => $item['macros']['calories_kcal'],
                'protein_g' => $item['macros']['protein_g'],
                'carbohydrate_g' => $item['macros']['carbohydrate_g'],
                'fat_g' => $item['macros']['fat_g'],
            ];
        }

        $this->gravarResultados($resultados);
    }

    private function pedirNomeCurto(string $descricao): ?string
    {
        $response = Http::timeout(30)->post(self::OLLAMA_URL, [
            'model' => self::OLLAMA_MODEL,
            'messages' => [
                ['role' => 'user', 'content' => $this->montarPrompt($descricao)],
            ],
            'format' => 'json',
            'stream' => false,
            'think' => false,
            'options' => ['temperature' => 0.1],
        ]);

        if (!$response->successful()) {
            Log::error('Ollama retornou erro no import TBCA', [
                'status' => $response->status(),
                'body' => $response->body(),
                'descricao' => $descricao,
            ]);

            return null;
        }

        $conteudo = $response->json('message.content');
        $decodificado = json_decode((string) $conteudo, true);
        $nomeCurto = $decodificado['nome_curto'] ?? null;

        if (!$nomeCurto) {
            Log::warning('Ollama devolveu resposta em formato inesperado no import TBCA', [
                'descricao' => $descricao,
                'conteudo_bruto' => $conteudo,
            ]);
        }

        return $nomeCurto;
    }

    /**
     * v11 — depois da v10 (que travou a remoção do substantivo principal:
     * espécie do peixe, vegetal da salada, vegetais da papinha), sobrou
     * duplicação residual em dois padrões mais finos: o corte da carne
     * (patinho/acém) e o modo de empanamento (à milanesa) também estavam
     * sumindo, colidindo variantes que têm macros diferentes. A v11
     * adiciona regras específicas pros dois. Validado em 12 iterações
     * (2 rodadas de 10) — não elimina 100% da duplicação (um cluster
     * específico "papa com arroz e brócolis" ainda colide às vezes), mas
     * cobre a maioria dos casos. O resto fica pra curadoria manual antes
     * de subir pro banco, não vale perseguir 100% via prompt.
     */
    private function montarPrompt(string $descricao): string
    {
        return <<<PROMPT
Gere um nome curto e natural (como uma pessoa digitaria num chat) pro alimento abaixo, em português do Brasil.

Regras:
- SEMPRE mantenha o substantivo/ingrediente PRINCIPAL do alimento (ex: a espécie do peixe, o vegetal principal da salada, o tipo de carne, os vegetais de uma papinha) — isso nunca pode ser removido nem trocado por um termo genérico, mesmo que a descrição tenha uma frase de preparo depois (ex: "frito", "com molho vinagrete"). Errado: "Peixe, água salgada, sardinha, filé, frito" -> "peixe frito" (perdeu "sardinha"). Certo: "sardinha frita".
- SEMPRE mantenha o corte/parte específica do animal quando a descrição citar um (ex: "patinho", "acém", "filé", "coxa", "peito") — isso diferencia variantes do mesmo prato com valores nutricionais diferentes.
- SEMPRE mantenha o modo de empanamento/cobertura quando a descrição citar (ex: "à milanesa", "empanado") — isso muda bastante o carboidrato/gordura, mesmo que o método de cocção final (frito) seja o mesmo.
- Remova nome científico em latim, marca comercial e QUALQUER menção a sal (com sal, sem sal, s/ sal, c/ sal) ou óleo/cebola/alho como tempero (c/ óleo, s/ óleo, cebola, alho) — nunca cite isso, nem mesmo pra dizer que não tem.
- Se a descrição citar 3 OU MAIS itens da MESMA categoria como parte de uma mistura ampla (ex: 5 tipos de fruto do mar numa mariscada), resuma sem listar todos. Mas se citar só 1 ou 2 ingredientes que definem a variante específica do prato (ex: os vegetais de uma papinha, tipo "cará e brócolis"), MANTENHA esses 1-2 ingredientes.
- Se a descrição citar termos de alta gordura (ex: leite de coco, azeite, manteiga, creme de leite, banha, óleo de dendê), mantenha SÓ o de maior impacto.
- IMPORTANTE: use APENAS palavras que aparecem na descrição original ou sinônimos diretos delas. NUNCA adicione um ingrediente, marca ou anotação que não esteja explicitamente escrito na descrição.

Descrição: "{$descricao}"

Responda só com um objeto JSON: {"nome_curto": "..."}
PROMPT;
    }

    private function carregarCodigosJaProcessados(): array
    {
        if (!file_exists($this->caminhoDestino) || filesize($this->caminhoDestino) === 0) {
            return [];
        }

        $conteudo = json_decode(file_get_contents($this->caminhoDestino), true) ?? [];

        return array_flip(array_column($conteudo, 'codigo'));
    }

    private function gravarResultados(array $novosResultados): void
    {
        $existentes = [];
        if (file_exists($this->caminhoDestino) && filesize($this->caminhoDestino) > 0) {
            $existentes = json_decode(file_get_contents($this->caminhoDestino), true) ?? [];
        }

        $combinados = array_merge($existentes, $novosResultados);

        file_put_contents(
            $this->caminhoDestino,
            json_encode($combinados, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    private function log(string $mensagem): void
    {
        echo $mensagem . PHP_EOL;
        Log::info("[OllamaImportService] {$mensagem}");
    }
}
