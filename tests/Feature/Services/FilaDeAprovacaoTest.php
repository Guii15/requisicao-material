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

    public function test_setor_sem_aprovador_vai_para_os_lideres_do_estoque(): void
    {
        [$ti] = $this->setorComAprovadores('TI');
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $liderEstoque = User::factory()->liderEstoque()->create();
        $this->abrirRequisicao(User::factory()->for($ti)->create());
        $daErica = $this->abrirRequisicao(User::factory()->for($rh)->create());

        $this->assertSame([$daErica->numero], $this->numerosNaFila($liderEstoque));
        $this->assertSame('Setor sem aprovador cadastrado', $this->fila->motivoSemAprovador($daErica));
    }

    public function test_admin_fica_fora_da_aprovacao_por_enquanto(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $admin = User::factory()->admin()->create();
        $this->abrirRequisicao(User::factory()->for($rh)->create());

        $this->assertSame([], $this->numerosNaFila($admin));
    }

    public function test_estoquista_que_nao_e_lider_nao_recebe_setor_sem_aprovador(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $estoquista = User::factory()->estoque()->create();
        $this->abrirRequisicao(User::factory()->for($rh)->create());

        $this->assertSame([], $this->numerosNaFila($estoquista));
    }

    public function test_pedido_do_unico_aprovador_vai_para_os_lideres_do_estoque(): void
    {
        $ninja = Setor::factory()->create(['nome' => 'NINJA PLACE']);
        $gustavo = User::factory()->for($ninja)->aprovadorDe($ninja)->create();
        $liderEstoque = User::factory()->liderEstoque()->create();
        $doGustavo = $this->abrirRequisicao($gustavo);
        $daEquipe = $this->abrirRequisicao(User::factory()->for($ninja)->create());

        $this->assertSame([$doGustavo->numero], $this->numerosNaFila($liderEstoque));
        $this->assertSame([$daEquipe->numero], $this->numerosNaFila($gustavo));
        $this->assertSame('O solicitante é o único aprovador do setor', $this->fila->motivoSemAprovador($doGustavo));
        $this->assertNull($this->fila->motivoSemAprovador($daEquipe));
    }

    public function test_aprovador_inativo_nao_conta(): void
    {
        $setor = Setor::factory()->create();
        User::factory()->for($setor)->aprovadorDe($setor)->inativo()->create();
        $liderEstoque = User::factory()->liderEstoque()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create());

        $this->assertSame([$requisicao->numero], $this->numerosNaFila($liderEstoque));
        $this->assertSame('Nenhum aprovador ativo no setor', $this->fila->motivoSemAprovador($requisicao));
    }

    public function test_lider_do_estoque_nunca_recebe_o_proprio_pedido(): void
    {
        // A Geisla é a única aprovadora cadastrada do Estoque: o pedido dela vai para os outros líderes.
        $estoque = Setor::factory()->create(['nome' => 'ESTOQUE']);
        $geisla = User::factory()->for($estoque)->liderEstoque()->aprovadorDe($estoque)->create();
        $kelber = User::factory()->for($estoque)->liderEstoque()->create();
        $daGeisla = $this->abrirRequisicao($geisla);

        $this->assertSame([], $this->numerosNaFila($geisla));
        $this->assertSame([$daGeisla->numero], $this->numerosNaFila($kelber));
        $this->assertSame([$kelber->nome], $this->fila->aprovadoresElegiveis($daGeisla)->pluck('nome')->all());
    }

    public function test_aprovadores_elegiveis_sao_os_do_setor_em_ordem_alfabetica(): void
    {
        [$ti, $lider, $sublider] = $this->setorComAprovadores();
        $lider->forceFill(['nome' => 'Rafael Moreira'])->save();
        $sublider->forceFill(['nome' => 'Camila Duarte'])->save();
        User::factory()->liderEstoque()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->assertSame(['Camila Duarte', 'Rafael Moreira'], $this->fila->aprovadoresElegiveis($requisicao)->pluck('nome')->all());
    }

    public function test_sem_aprovador_no_setor_os_elegiveis_sao_os_lideres_do_estoque_ativos(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        User::factory()->liderEstoque()->create(['nome' => 'Sergio Alves']);
        User::factory()->liderEstoque()->create(['nome' => 'Geisla Luiz']);
        User::factory()->liderEstoque()->inativo()->create(['nome' => 'Kelber Desligado']);
        $requisicao = $this->abrirRequisicao(User::factory()->for($rh)->create());

        $this->assertSame(['Geisla Luiz', 'Sergio Alves'], $this->fila->aprovadoresElegiveis($requisicao)->pluck('nome')->all());
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
