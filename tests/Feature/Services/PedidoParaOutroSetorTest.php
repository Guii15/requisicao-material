<?php

namespace Tests\Feature\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\AssinaturaService;
use App\Services\FilaDeAprovacao;
use App\Services\FilaDeSeparacao;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * Decisão de 17/11/2026: pedido feito para outro setor (não o Estoque) não passa por aprovação
 * nenhuma; cai direto para esse setor separar e entregar. No Uso e Consumo a Meire dá a baixa.
 */
class PedidoParaOutroSetorTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private RequisicaoWorkflow $workflow;

    private Setor $ti;

    private User $doTi;

    private User $estoquista;

    private User $kelber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->workflow = app(RequisicaoWorkflow::class);
        $estoque = Setor::factory()->create(['nome' => 'ESTOQUE']);
        $this->ti = Setor::factory()->create(['nome' => 'TI']);
        $this->doTi = User::factory()->for($this->ti)->create();
        $this->estoquista = User::factory()->for($estoque)->estoque()->create();
        $this->kelber = User::factory()->for($estoque)->liderEstoque()->create();
    }

    private function pedirParaOTi(string $tipo): Requisicao
    {
        $entrada = Setor::factory()->create(['nome' => 'ENTRADA']);
        $dados = ['tipo' => $tipo, 'setor_destino_id' => $this->ti->id];
        if ($tipo === 'USO_CONSUMO') {
            $dados['data_prevista_devolucao'] = null;
        }

        return $this->abrirRequisicao(User::factory()->for($entrada)->create(), $dados);
    }

    public function test_uso_e_consumo_para_outro_setor_vai_direto_para_esse_setor_e_a_meire_da_baixa(): void
    {
        $requisicao = $this->pedirParaOTi('USO_CONSUMO');

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->status);
        $this->assertSame(0, app(FilaDeAprovacao::class)->para($this->kelber)->count());
        $this->assertSame([$requisicao->id], app(FilaDeSeparacao::class)->para($this->doTi)->pluck('id')->all());
        $this->assertSame(0, app(FilaDeSeparacao::class)->para($this->estoquista)->count());
        $this->assertTrue($this->doTi->can('view', $requisicao));
        $this->assertTrue(Gate::forUser($this->doTi)->allows('acessar-separacao'));
        $this->assertFalse($this->estoquista->can('separar', $requisicao));
        $this->assertTrue(app(AssinaturaService::class)->verificar($requisicao)->integro);

        $this->workflow->separar($requisicao, $this->doTi, $requisicao->itens->pluck('qtd_solicitada', 'id')->all(), null);
        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::AGUARDANDO_BAIXA, $requisicao->status);

        $this->workflow->darBaixa($requisicao, User::factory()->responsavelBaixa()->create(), null, null);
        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::BAIXADA, $requisicao->status);
        $this->assertTrue(app(AssinaturaService::class)->verificar($requisicao)->integro);
    }

    public function test_teste_para_outro_setor_esse_setor_entrega_e_confere_a_devolucao(): void
    {
        $requisicao = $this->pedirParaOTi('TESTE');

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->status);
        $this->assertFalse($this->estoquista->can('separarEntregar', $requisicao));

        $this->workflow->separarEntregar($requisicao, $this->doTi, $requisicao->itens->pluck('qtd_solicitada', 'id')->all(), 'Maria da Silva');
        $requisicao->refresh();

        $this->assertSame(StatusRequisicao::EM_POSSE, $requisicao->status);
        $this->assertTrue($this->doTi->can('devolver', $requisicao));
        $this->assertFalse($this->estoquista->can('devolver', $requisicao));
        $this->assertTrue(app(AssinaturaService::class)->verificar($requisicao)->integro);
    }

    public function test_pedido_para_o_estoque_continua_com_os_lideres_do_estoque(): void
    {
        $requisicao = $this->abrirRequisicao($this->doTi);

        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, $requisicao->status);
        $this->assertTrue($this->kelber->can('aprovar', $requisicao));
        $this->assertFalse(Gate::forUser($this->doTi)->allows('acessar-separacao'));
    }
}
