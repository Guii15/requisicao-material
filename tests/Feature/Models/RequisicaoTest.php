<?php

namespace Tests\Feature\Models;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Exceptions\RegistroProtegidoException;
use App\Models\Requisicao;
use App\Models\RequisicaoItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisicaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tipo_e_status_sao_convertidos_para_enum(): void
    {
        $requisicao = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::APROVADA)->create();

        $requisicao->refresh();

        $this->assertSame(TipoRequisicao::USO_CONSUMO, $requisicao->tipo);
        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->status);
    }

    public function test_campos_controlados_pelo_workflow_nao_sao_preenchiveis_em_massa(): void
    {
        $requisicao = new Requisicao;

        foreach ([
            'numero', 'status', 'solicitante_id', 'setor_id', 'setor_destino_id', 'finalidade_complemento_estoque',
            'aprovado_por_id', 'aprovado_em', 'motivo_reprovacao', 'separado_por_id', 'separado_em',
            'liberado_por_id', 'liberado_em', 'motivo_reprovacao_estoque', 'entregue_por_id', 'entregue_em',
            'retirado_por_user_id', 'retirado_por_nome', 'recebido_em', 'devolucao_conferida_por_id',
            'devolucao_conferida_em', 'baixa_documento_winthor', 'baixa_por_id', 'baixa_em', 'baixa_observacao',
            'cancelado_por_id', 'cancelado_em', 'motivo_cancelamento',
        ] as $campo) {
            $this->assertFalse($requisicao->isFillable($campo), "{$campo} não pode ser preenchível em massa");
        }
    }

    public function test_requisicao_nao_pode_ser_alterada_fora_do_workflow(): void
    {
        $requisicao = Requisicao::factory()->create();

        $this->expectException(RegistroProtegidoException::class);

        $requisicao->forceFill(['status' => StatusRequisicao::APROVADA])->save();
    }

    public function test_requisicao_pode_ser_alterada_dentro_do_workflow(): void
    {
        $requisicao = Requisicao::factory()->create();

        Requisicao::viaWorkflow(fn () => $requisicao->forceFill(['status' => StatusRequisicao::APROVADA])->save());

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    public function test_bloqueio_volta_depois_do_workflow_mesmo_com_erro(): void
    {
        $requisicao = Requisicao::factory()->create();

        try {
            Requisicao::viaWorkflow(fn () => throw new \RuntimeException('falhou'));
        } catch (\RuntimeException) {
        }

        $this->expectException(RegistroProtegidoException::class);
        $requisicao->forceFill(['justificativa' => 'alterada'])->save();
    }

    public function test_item_nao_pode_ser_alterado_fora_do_workflow(): void
    {
        $item = RequisicaoItem::factory()->create();

        $this->expectException(RegistroProtegidoException::class);

        $item->forceFill(['descricao' => 'outro produto'])->save();
    }

    public function test_requisicao_nunca_e_excluida(): void
    {
        $requisicao = Requisicao::factory()->create();

        $this->expectException(RegistroProtegidoException::class);

        Requisicao::viaWorkflow(fn () => $requisicao->delete());
    }

    public function test_item_nunca_e_excluido(): void
    {
        $item = RequisicaoItem::factory()->create();

        $this->expectException(RegistroProtegidoException::class);

        Requisicao::viaWorkflow(fn () => $item->delete());
    }

    public function test_relacionamentos_basicos(): void
    {
        $requisicao = Requisicao::factory()->create();
        RequisicaoItem::factory()->count(2)->for($requisicao)->create();

        $requisicao->load(['itens', 'solicitante', 'setor']);

        $this->assertCount(2, $requisicao->itens);
        $this->assertSame($requisicao->solicitante->setor_id, $requisicao->setor->id);
    }

    public function test_quantidades_mantem_tres_casas_decimais(): void
    {
        $item = RequisicaoItem::factory()->create(['qtd_solicitada' => '2.5']);

        $this->assertSame('2.500', $item->fresh()->qtd_solicitada);
    }
}
