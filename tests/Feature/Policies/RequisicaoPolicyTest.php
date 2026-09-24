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

    public function test_estoque_separa_somente_o_que_esta_aprovado(): void
    {
        $estoquista = User::factory()->estoque()->create();

        foreach (S::cases() as $status) {
            $requisicao = Requisicao::factory()->status($status)->create();

            $this->assertSame($status === S::APROVADA, $estoquista->can('separar', $requisicao), $status->value);
        }
    }

    public function test_lider_do_estoque_tambem_separa(): void
    {
        $liderEstoque = User::factory()->liderEstoque()->create();
        $requisicao = Requisicao::factory()->status(S::APROVADA)->create();

        $this->assertTrue($liderEstoque->can('separar', $requisicao));
    }

    public function test_quem_nao_e_do_estoque_nao_separa(): void
    {
        $requisicao = Requisicao::factory()->status(S::APROVADA)->create();

        $this->assertFalse(User::factory()->create()->can('separar', $requisicao));
        $this->assertFalse($requisicao->solicitante->can('separar', $requisicao));
    }

    public function test_estoquista_inativo_nao_separa(): void
    {
        $estoquista = User::factory()->estoque()->inativo()->create();
        $requisicao = Requisicao::factory()->status(S::APROVADA)->create();

        $this->assertFalse($estoquista->can('separar', $requisicao));
    }

    public function test_liberar_e_so_lider_estoque_uso_e_consumo_e_nunca_quem_ja_agiu(): void
    {
        $aprovador = User::factory()->create();
        $separador = User::factory()->create();
        $requisicao = Requisicao::factory()->usoConsumo()->status(S::AGUARDANDO_LIBERACAO_ESTOQUE)->create([
            'aprovado_por_id' => $aprovador->id,
            'separado_por_id' => $separador->id,
        ]);

        $this->assertTrue(User::factory()->liderEstoque()->create()->can('liberar', $requisicao));
        $this->assertFalse(User::factory()->estoque()->create()->can('liberar', $requisicao), 'estoque comum não é líder');

        $aprovador->forceFill(['is_lider_estoque' => true, 'is_estoque' => true])->save();
        $separador->forceFill(['is_lider_estoque' => true, 'is_estoque' => true])->save();
        $this->assertFalse($aprovador->fresh()->can('liberar', $requisicao), 'quem aprovou não libera');
        $this->assertFalse($separador->fresh()->can('liberar', $requisicao), 'quem separou não libera');
    }

    public function test_teste_nao_tem_liberacao(): void
    {
        $requisicao = Requisicao::factory()->status(S::AGUARDANDO_LIBERACAO_ESTOQUE)->create();

        $this->assertFalse(User::factory()->liderEstoque()->create()->can('liberar', $requisicao));
    }

    public function test_entregar_e_qualquer_um_do_estoque_com_status_certo(): void
    {
        $pronta = Requisicao::factory()->status(S::PRONTA_PARA_RETIRADA)->create();
        $aprovada = Requisicao::factory()->status(S::APROVADA)->create();

        $this->assertTrue(User::factory()->estoque()->create()->can('entregar', $pronta));
        $this->assertFalse(User::factory()->estoque()->create()->can('entregar', $aprovada));
        $this->assertFalse(User::factory()->create()->can('entregar', $pronta));
    }

    public function test_confirmar_recebimento_e_so_o_solicitante_no_teste_em_posse(): void
    {
        $requisicao = Requisicao::factory()->status(S::EM_POSSE)->create();
        $usoConsumo = Requisicao::factory()->usoConsumo()->status(S::AGUARDANDO_BAIXA)->create();

        $this->assertTrue($requisicao->solicitante->can('confirmarRecebimento', $requisicao));
        $this->assertFalse(User::factory()->create()->can('confirmarRecebimento', $requisicao));
        $this->assertFalse($usoConsumo->solicitante->can('confirmarRecebimento', $usoConsumo), 'uso e consumo não tem recebimento');
    }

    public function test_devolver_e_qualquer_um_do_estoque_no_teste_em_posse(): void
    {
        $requisicao = Requisicao::factory()->status(S::EM_POSSE)->create();

        $this->assertTrue(User::factory()->estoque()->create()->can('devolver', $requisicao));
        $this->assertFalse(User::factory()->create()->can('devolver', $requisicao));
    }

    public function test_dar_baixa_e_a_responsavel_menos_no_proprio_pedido(): void
    {
        $requisicao = Requisicao::factory()->usoConsumo()->status(S::AGUARDANDO_BAIXA)->create();
        $erica = User::factory()->responsavelBaixa()->create();
        $proprioPedido = Requisicao::factory()->usoConsumo()->status(S::AGUARDANDO_BAIXA)->create(['solicitante_id' => $erica->id]);

        $this->assertTrue($erica->can('darBaixa', $requisicao));
        $this->assertFalse($erica->can('darBaixa', $proprioPedido));
    }

    public function test_admin_da_baixa_quando_a_responsavel_e_a_propria_solicitante(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->usoConsumo()->status(S::AGUARDANDO_BAIXA)->create(['solicitante_id' => $erica->id]);
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('darBaixa', $requisicao));
        $this->assertFalse(User::factory()->admin()->create()->can('darBaixa', Requisicao::factory()->usoConsumo()->status(S::AGUARDANDO_BAIXA)->create()), 'admin não entra nas baixas normais');
    }
}
