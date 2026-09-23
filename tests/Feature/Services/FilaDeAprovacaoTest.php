<?php

namespace Tests\Feature\Services;

use App\Models\Setor;
use App\Models\User;
use App\Services\FilaDeAprovacao;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class FilaDeAprovacaoTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private FilaDeAprovacao $fila;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->fila = app(FilaDeAprovacao::class);
    }

    /**
     * @return list<string>
     */
    private function numerosNaFila(User $user): array
    {
        return $this->fila->para($user)->orderBy('id')->pluck('numero')->all();
    }

    public function test_aprovador_ve_as_requisicoes_do_setor_menos_as_proprias(): void
    {
        [$ti, $lider, $sublider] = $this->setorComAprovadores();
        $doColega = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $doLider = $this->abrirRequisicao($lider);

        $this->assertSame([$doColega->numero], $this->numerosNaFila($lider));
        $this->assertSame([$doColega->numero, $doLider->numero], $this->numerosNaFila($sublider));
    }

    public function test_aprovador_nao_ve_outros_setores(): void
    {
        [$ti] = $this->setorComAprovadores('TI');
        [, $liderFinanceiro] = $this->setorComAprovadores('FINANCEIRO');
        $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->assertSame([], $this->numerosNaFila($liderFinanceiro));
    }

    public function test_requisicao_decidida_sai_da_fila(): void
    {
        [$ti, $lider, $sublider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        app(RequisicaoWorkflow::class)->aprovar($requisicao, $lider, 'password');

        $this->assertSame([], $this->numerosNaFila($sublider));
    }

    public function test_admin_ve_somente_o_que_nao_tem_aprovador_elegivel(): void
    {
        [$ti] = $this->setorComAprovadores('TI');
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $admin = User::factory()->admin()->for($ti)->create();
        $this->abrirRequisicao(User::factory()->for($ti)->create());
        $daErica = $this->abrirRequisicao(User::factory()->for($rh)->create());

        $this->assertSame([$daErica->numero], $this->numerosNaFila($admin));
        $this->assertSame('Setor sem aprovador cadastrado', $this->fila->motivoFilaAdmin($daErica));
    }

    public function test_pedido_do_unico_aprovador_vai_para_o_admin(): void
    {
        $ninja = Setor::factory()->create(['nome' => 'NINJA PLACE']);
        $gustavo = User::factory()->for($ninja)->aprovadorDe($ninja)->create();
        $admin = User::factory()->admin()->create();
        $doGustavo = $this->abrirRequisicao($gustavo);
        $daEquipe = $this->abrirRequisicao(User::factory()->for($ninja)->create());

        $this->assertSame([$doGustavo->numero], $this->numerosNaFila($admin));
        $this->assertSame([$daEquipe->numero], $this->numerosNaFila($gustavo));
        $this->assertSame('O solicitante é o único aprovador do setor', $this->fila->motivoFilaAdmin($doGustavo));
        $this->assertNull($this->fila->motivoFilaAdmin($daEquipe));
    }

    public function test_aprovador_inativo_nao_conta(): void
    {
        $setor = Setor::factory()->create();
        User::factory()->for($setor)->aprovadorDe($setor)->inativo()->create();
        $admin = User::factory()->admin()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create());

        $this->assertSame([$requisicao->numero], $this->numerosNaFila($admin));
    }

    public function test_lider_do_estoque_ve_o_showroom_quando_e_aprovador_dele(): void
    {
        [$estoque, $geisla] = $this->setorComAprovadores('ESTOQUE');
        $showroom = Setor::factory()->create(['nome' => 'SHOWROOM']);
        $showroom->aprovadores()->attach($geisla->id, ['papel' => 'LIDER']);
        $admin = User::factory()->admin()->create();
        $doShowroom = $this->abrirRequisicao(User::factory()->for($showroom)->create());

        $this->assertSame([$doShowroom->numero], $this->numerosNaFila($geisla));
        $this->assertSame([], $this->numerosNaFila($admin));
    }
}
