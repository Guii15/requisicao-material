<?php

namespace Tests\Feature\Http;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * Wiring das telas de Liberação, Entrega, Recebimento, Devolução e Baixa (rota -> controller
 * -> view -> redirect). As regras de negócio de cada uma já têm teste próprio e mais completo
 * em RequisicaoWorkflowTest e RequisicaoPolicyTest; aqui é só conferir que a tela funciona.
 */
class EstoqueFluxosTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private RequisicaoWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->workflow = app(RequisicaoWorkflow::class);
    }

    private const ASSINATURA_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    private function requisicaoAguardandoLiberacao(string $setor = 'TI'): Requisicao
    {
        [$ti, $lider] = $this->setorComAprovadores($setor);
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        $this->workflow->aprovar($requisicao, $lider, 'password');
        $this->workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), $requisicao->fresh()->itens->pluck('qtd_solicitada', 'id')->all(), 'password');

        return $requisicao->fresh();
    }

    private function requisicaoProntaParaRetirada(string $setor = 'TI'): Requisicao
    {
        [$ti, $lider] = $this->setorComAprovadores($setor);
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $this->workflow->aprovar($requisicao, $lider, 'password');
        $this->workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), $requisicao->fresh()->itens->pluck('qtd_solicitada', 'id')->all(), 'password');

        return $requisicao->fresh();
    }

    private function requisicaoEmPosse(): Requisicao
    {
        $requisicao = $this->requisicaoProntaParaRetirada();
        $this->workflow->entregar($requisicao, User::factory()->estoque()->create(), 'Fulano', self::ASSINATURA_PNG, 'password');

        return $requisicao->fresh();
    }

    private function requisicaoAguardandoBaixa(): Requisicao
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();
        $lider = User::factory()->liderEstoque()->create();
        $this->workflow->liberar($requisicao, $lider, 'password');
        $this->workflow->entregar($requisicao->fresh(), User::factory()->estoque()->create(), 'Fulano', self::ASSINATURA_PNG, 'password');

        return $requisicao->fresh();
    }

    // ---- Liberação --------------------------------------------------------------------------

    public function test_lider_do_estoque_ve_a_fila_de_liberacao(): void
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();

        $this->actingAs(User::factory()->liderEstoque()->create())->get('/liberacao')
            ->assertOk()
            ->assertSee($requisicao->numero)
            ->assertSee('data-contador-liberacao="1"', false);
    }

    public function test_liberar_pela_tela(): void
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();

        $this->actingAs(User::factory()->liderEstoque()->create())
            ->post("/requisicoes/{$requisicao->numero}/liberar", ['senha' => 'password'])
            ->assertRedirect('/liberacao')
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::PRONTA_PARA_RETIRADA, $requisicao->fresh()->status);
    }

    // ---- Entrega ------------------------------------------------------------------------------

    public function test_estoque_ve_a_fila_de_entrega(): void
    {
        $requisicao = $this->requisicaoProntaParaRetirada();

        $this->actingAs(User::factory()->estoque()->create())->get('/entrega')
            ->assertOk()
            ->assertSee($requisicao->numero);
    }

    public function test_entregar_pela_tela(): void
    {
        $requisicao = $this->requisicaoProntaParaRetirada();

        $this->actingAs(User::factory()->estoque()->create())
            ->post("/requisicoes/{$requisicao->numero}/entregar", [
                'retirado_por_nome' => 'Maria da Silva',
                'assinatura' => self::ASSINATURA_PNG,
                'senha' => 'password',
            ])
            ->assertRedirect('/entrega')
            ->assertSessionHas('sucesso');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::EM_POSSE, $requisicao->status);
        $this->assertSame('Maria da Silva', $requisicao->retirado_por_nome);
    }

    // ---- Recebimento ---------------------------------------------------------------------------

    public function test_solicitante_confirma_recebimento_pela_tela(): void
    {
        $requisicao = $this->requisicaoEmPosse();

        $this->actingAs($requisicao->solicitante)
            ->post("/requisicoes/{$requisicao->numero}/confirmar-recebimento", ['senha' => 'password'])
            ->assertRedirect("/requisicoes/{$requisicao->numero}")
            ->assertSessionHas('sucesso');

        $this->assertNotNull($requisicao->fresh()->recebido_em);
    }

    // ---- Devolução -----------------------------------------------------------------------------

    public function test_estoque_ve_a_fila_de_devolucao(): void
    {
        $requisicao = $this->requisicaoEmPosse();

        $this->actingAs(User::factory()->estoque()->create())->get('/devolucao')
            ->assertOk()
            ->assertSee($requisicao->numero);
    }

    public function test_devolver_pela_tela(): void
    {
        $requisicao = $this->requisicaoEmPosse();
        $itens = $requisicao->itens->mapWithKeys(fn ($item) => [
            $item->id => ['ok' => (string) $item->qtd_solicitada, 'defeito' => '0', 'nao_devolvida' => '0'],
        ])->all();

        $this->actingAs(User::factory()->estoque()->create())
            ->post("/requisicoes/{$requisicao->numero}/devolver", ['itens' => $itens, 'senha' => 'password'])
            ->assertRedirect('/devolucao')
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::DEVOLVIDA, $requisicao->fresh()->status);
    }

    // ---- Baixa ---------------------------------------------------------------------------------

    public function test_responsavel_pela_baixa_ve_a_fila(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();

        $this->actingAs(User::factory()->responsavelBaixa()->create())->get('/baixa')
            ->assertOk()
            ->assertSee($requisicao->numero);
    }

    public function test_dar_baixa_pela_tela(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();

        $this->actingAs(User::factory()->responsavelBaixa()->create())
            ->post("/requisicoes/{$requisicao->numero}/dar-baixa", ['documento' => 'NF-123', 'senha' => 'password'])
            ->assertRedirect('/baixa')
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::BAIXADA, $requisicao->fresh()->status);
    }

    // ---- Botões condicionais no detalhe --------------------------------------------------------

    public function test_detalhe_mostra_botoes_certos_por_etapa(): void
    {
        $liberacao = $this->requisicaoAguardandoLiberacao('TI-A');
        $entrega = $this->requisicaoProntaParaRetirada('TI-B');

        $this->actingAs(User::factory()->liderEstoque()->create())
            ->get("/requisicoes/{$liberacao->numero}")
            ->assertSee('Liberar');

        $this->actingAs(User::factory()->estoque()->create())
            ->get("/requisicoes/{$entrega->numero}")
            ->assertSee('Entregar');
    }
}
