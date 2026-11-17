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

    public function test_todo_pedido_vai_para_os_lideres_do_estoque_de_qualquer_setor(): void
    {
        // Decisão de 17/11/2026: sem aprovação do líder do setor, o pedido vai direto para o estoque.
        [$ti, $liderTi] = $this->setorComAprovadores('TI', lideresDoEstoque: false);
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $liderEstoque = User::factory()->liderEstoque()->create();
        $doTi = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $doRh = $this->abrirRequisicao(User::factory()->for($rh)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);

        $this->assertSame([$doTi->numero, $doRh->numero], $this->numerosNaFila($liderEstoque));
        $this->assertSame([], $this->numerosNaFila($liderTi));
        $this->assertNull($this->fila->motivoSemAprovador($doRh));
    }

    public function test_requisicao_decidida_sai_da_fila(): void
    {
        [$ti] = $this->setorComAprovadores();
        $kelber = User::factory()->liderEstoque()->create();
        $sergio = User::factory()->liderEstoque()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        app(RequisicaoWorkflow::class)->aprovar($requisicao, $kelber, 'password');

        $this->assertSame([], $this->numerosNaFila($sergio));
    }

    public function test_admin_e_estoquista_ficam_fora_da_aprovacao(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $this->abrirRequisicao(User::factory()->for($rh)->create());

        $this->assertSame([], $this->numerosNaFila(User::factory()->admin()->create()));
        $this->assertSame([], $this->numerosNaFila(User::factory()->estoque()->create()));
    }

    public function test_lider_do_estoque_nunca_recebe_o_proprio_pedido(): void
    {
        $estoque = Setor::factory()->create(['nome' => 'ESTOQUE']);
        $geisla = User::factory()->for($estoque)->liderEstoque()->aprovadorDe($estoque)->create();
        $kelber = User::factory()->for($estoque)->liderEstoque()->create();
        $daGeisla = $this->abrirRequisicao($geisla);

        $this->assertSame([], $this->numerosNaFila($geisla));
        $this->assertSame([$daGeisla->numero], $this->numerosNaFila($kelber));
        $this->assertSame([$kelber->nome], $this->fila->aprovadoresElegiveis($daGeisla)->pluck('nome')->all());
    }

    public function test_elegiveis_sao_os_lideres_do_estoque_ativos_em_ordem_alfabetica(): void
    {
        [$ti] = $this->setorComAprovadores('TI', lideresDoEstoque: false);
        User::factory()->liderEstoque()->create(['nome' => 'Sergio Alves']);
        User::factory()->liderEstoque()->create(['nome' => 'Geisla Luiz']);
        User::factory()->liderEstoque()->inativo()->create(['nome' => 'Kelber Desligado']);
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->assertSame(['Geisla Luiz', 'Sergio Alves'], $this->fila->aprovadoresElegiveis($requisicao)->pluck('nome')->all());
    }
}
