<?php

namespace Tests\Feature\Policies;

use App\Enums\StatusRequisicao as S;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequisicaoPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_estoque_nunca_ve_requisicao_nao_aprovada(): void
    {
        $estoquista = User::factory()->estoque()->create();
        $liderEstoque = User::factory()->liderEstoque()->create();

        foreach (S::cases() as $status) {
            $requisicao = Requisicao::factory()->status($status)->create();
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

    public function test_usuario_inativo_nao_abre_requisicao(): void
    {
        $this->assertTrue(User::factory()->create()->can('create', Requisicao::class));
        $this->assertFalse(User::factory()->inativo()->create()->can('create', Requisicao::class));
    }
}
