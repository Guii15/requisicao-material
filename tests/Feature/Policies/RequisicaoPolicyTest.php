<?php

namespace Tests\Feature\Policies;

use App\Enums\StatusRequisicao as S;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class RequisicaoPolicyTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    public function test_estoque_nunca_ve_requisicao_nao_aprovada(): void
    {
        $estoquista = User::factory()->estoque()->create();
        $liderEstoque = User::factory()->liderEstoque()->create();
        // Setor com aprovador: a aprovação não cai para os líderes do estoque.
        $solicitante = User::factory()->create();
        User::factory()->aprovadorDe($solicitante->setor)->create();

        foreach (S::cases() as $status) {
            $requisicao = Requisicao::factory()->for($solicitante, 'solicitante')->status($status)->create();
            $esperado = ! in_array($status, [S::AGUARDANDO_APROVACAO, S::REPROVADA, S::CANCELADA], true);

            $this->assertSame($esperado, $estoquista->can('view', $requisicao), "estoque / {$status->value}");
            $this->assertSame($esperado, $liderEstoque->can('view', $requisicao), "líder do estoque / {$status->value}");
        }
    }

    public function test_responsavel_pela_baixa_so_ve_uso_e_consumo_a_partir_de_aguardando_baixa(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();

        foreach (S::cases() as $status) {
            $usoConsumo = Requisicao::factory()->usoConsumo()->status($status)->create();
            $teste = Requisicao::factory()->status($status)->create();
            $esperado = in_array($status, [S::AGUARDANDO_BAIXA, S::BAIXADA], true);

            $this->assertSame($esperado, $erica->can('view', $usoConsumo), "uso e consumo / {$status->value}");
            $this->assertFalse($erica->can('view', $teste), "teste / {$status->value}");
        }
    }

    public function test_solicitante_ve_a_propria_e_nao_ve_a_dos_outros(): void
    {
        $requisicao = Requisicao::factory()->create();
        $colega = User::factory()->for($requisicao->setor)->create();

        $this->assertTrue($requisicao->solicitante->can('view', $requisicao));
        $this->assertFalse($colega->can('view', $requisicao));
    }

    public function test_aprovador_ve_as_do_setor_que_aprova(): void
    {
        $setor = Setor::factory()->create();
        $aprovador = User::factory()->aprovadorDe($setor)->create();
        $doSetor = Requisicao::factory()->create(['setor_id' => $setor->id]);
        $deOutroSetor = Requisicao::factory()->create();

        $this->assertTrue($aprovador->can('view', $doSetor));
        $this->assertFalse($aprovador->can('view', $deOutroSetor));
    }

    public function test_admin_ve_todas(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('view', Requisicao::factory()->create()));
    }

    public function test_admin_ve_e_cancela_mas_nao_aprova(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $admin = User::factory()->admin()->create();
        $requisicao = Requisicao::factory()->create(['setor_id' => $rh->id]);

        $this->assertTrue($admin->can('view', $requisicao));
        $this->assertTrue($admin->can('cancelar', $requisicao));
        $this->assertFalse($admin->can('aprovar', $requisicao));
        $this->assertFalse($admin->can('reprovar', $requisicao));
    }

    public function test_lider_do_estoque_ve_e_aprova_somente_setor_sem_aprovador(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $liderEstoque = User::factory()->liderEstoque()->create();
        $semAprovador = Requisicao::factory()->create(['setor_id' => $rh->id]);
        $comAprovador = Requisicao::factory()->create();
        User::factory()->aprovadorDe($comAprovador->setor)->create();

        $this->assertTrue($liderEstoque->can('view', $semAprovador));
        $this->assertTrue($liderEstoque->can('aprovar', $semAprovador));
        $this->assertFalse($liderEstoque->can('view', $comAprovador));
        $this->assertFalse($liderEstoque->can('aprovar', $comAprovador));
    }

    public function test_quem_assinou_continua_vendo_depois_da_decisao(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $liderEstoque = User::factory()->liderEstoque()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($rh)->create());

        app(RequisicaoWorkflow::class)->reprovar($requisicao, $liderEstoque, 'Pedido em duplicidade.', 'password');

        $this->assertSame(S::REPROVADA, $requisicao->fresh()->status);
        $this->assertTrue($liderEstoque->can('view', $requisicao->fresh()));
    }

    public function test_usuario_inativo_nao_abre_requisicao(): void
    {
        $this->assertTrue(User::factory()->create()->can('create', Requisicao::class));
        $this->assertFalse(User::factory()->inativo()->create()->can('create', Requisicao::class));
    }
}
