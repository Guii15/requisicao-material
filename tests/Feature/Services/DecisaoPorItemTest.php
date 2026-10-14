<?php

namespace Tests\Feature\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * Quem aprova (e a Erica, no pedido de compra) decide item por item: um item recusado
 * sai do fluxo e os outros seguem. Se todos forem recusados, a requisição é reprovada.
 */
class DecisaoPorItemTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private RequisicaoWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-09-24 10:00:00');
        $this->workflow = app(RequisicaoWorkflow::class);
    }

    /**
     * @return array{0: Requisicao, 1: User}
     */
    private function usoConsumoDeDoisItens(): array
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $solicitante = User::factory()->for($setor)->create();
        $requisicao = $this->abrirRequisicao($solicitante, ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);

        return [$requisicao, $lider];
    }

    public function test_aprovador_recusa_um_item_e_aprova_o_outro(): void
    {
        [$requisicao, $lider] = $this->usoConsumoDeDoisItens();
        [$primeiro, $segundo] = $requisicao->itens;

        $this->workflow->aprovar($requisicao, $lider, null, [$segundo->id => 'Item em falta no estoque']);

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
        $this->assertNull($primeiro->fresh()->recusado_em);
        $recusado = $segundo->fresh();
        $this->assertNotNull($recusado->recusado_em);
        $this->assertSame($lider->id, $recusado->recusado_por_id);
        $this->assertSame('Item em falta no estoque', $recusado->motivo_recusa);
    }

    public function test_recusar_todos_os_itens_reprova_a_requisicao(): void
    {
        [$requisicao, $lider] = $this->usoConsumoDeDoisItens();
        $recusas = $requisicao->itens->mapWithKeys(fn ($item) => [$item->id => 'Não vai mais ser usado'])->all();

        $this->workflow->aprovar($requisicao, $lider, null, $recusas);

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::REPROVADA, $requisicao->status);
        $this->assertStringContainsString('Não vai mais ser usado', $requisicao->motivo_reprovacao);
    }

    public function test_recusa_de_item_exige_motivo(): void
    {
        [$requisicao, $lider] = $this->usoConsumoDeDoisItens();

        try {
            $this->workflow->aprovar($requisicao, $lider, null, [$requisicao->itens->last()->id => '']);
            $this->fail('Deveria exigir o motivo da recusa.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, $requisicao->fresh()->status);
        $this->assertNull($requisicao->itens->last()->fresh()->recusado_em);
    }

    public function test_item_de_outra_requisicao_e_ignorado(): void
    {
        [$requisicao, $lider] = $this->usoConsumoDeDoisItens();
        $alheio = Requisicao::factory()->usoConsumo()->create()->itens()->create([
            'descricao' => 'Item de outra requisição', 'unidade' => 'UN', 'qtd_solicitada' => 1,
        ]);

        $this->workflow->aprovar($requisicao, $lider, null, [$alheio->id => 'Motivo qualquer válido']);

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
        $this->assertNull($alheio->fresh()->recusado_em);
    }

    public function test_separacao_considera_so_os_itens_aprovados(): void
    {
        [$requisicao, $lider] = $this->usoConsumoDeDoisItens();
        [$primeiro, $segundo] = $requisicao->itens;
        $this->workflow->aprovar($requisicao, $lider, null, [$segundo->id => 'Item em falta no estoque']);

        $this->workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), [$primeiro->id => '1'], null);

        $this->assertSame(StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE, $requisicao->fresh()->status);
        $this->assertEquals(1, $primeiro->fresh()->qtd_separada);
        $this->assertNull($segundo->fresh()->qtd_separada);
    }

    public function test_responsavel_aprova_a_compra_recusando_um_item(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create();
        $a = $requisicao->itens()->create(['descricao' => 'SSD NVMe 1TB', 'unidade' => 'UN', 'qtd_solicitada' => 1]);
        $b = $requisicao->itens()->create(['descricao' => 'Memória DDR4 16GB', 'unidade' => 'UN', 'qtd_solicitada' => 2]);

        $this->workflow->decidirCompra(
            $requisicao, $erica, true, null, 'A_VISTA', null,
            recusados: [$b->id => 'Já temos em estoque'],
            comprovante: UploadedFile::fake()->image('winthor.png'),
        );

        $this->assertSame(StatusRequisicao::COMPRA_APROVADA, $requisicao->fresh()->status);
        $this->assertNull($a->fresh()->recusado_em);
        $this->assertSame('Já temos em estoque', $b->fresh()->motivo_recusa);
        $this->assertSame($erica->id, $b->fresh()->recusado_por_id);
    }

    public function test_responsavel_recusando_todos_os_itens_reprova_a_compra(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create();
        $a = $requisicao->itens()->create(['descricao' => 'SSD NVMe 1TB', 'unidade' => 'UN', 'qtd_solicitada' => 1]);
        $b = $requisicao->itens()->create(['descricao' => 'Memória DDR4 16GB', 'unidade' => 'UN', 'qtd_solicitada' => 2]);

        $this->workflow->decidirCompra(
            $requisicao, $erica, true, null, 'A_VISTA', null,
            recusados: [$a->id => 'Sem orçamento este mês', $b->id => 'Sem orçamento este mês'],
        );

        $this->assertSame(StatusRequisicao::COMPRA_REPROVADA, $requisicao->fresh()->status);
        $this->assertStringContainsString('Sem orçamento este mês', $requisicao->fresh()->compra_observacao);
    }
}
