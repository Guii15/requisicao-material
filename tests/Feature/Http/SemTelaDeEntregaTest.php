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
 * A tela de Entrega saiu. No Teste, separar e entregar é uma tela só; no Uso e Consumo,
 * depois que o líder libera a requisição vai direto para a baixa.
 */
class SemTelaDeEntregaTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
    }

    public function test_tela_de_entrega_nao_existe_mais(): void
    {
        $this->actingAs(User::factory()->estoque()->create())->get('/entrega')->assertNotFound();
    }

    public function test_menu_do_estoque_nao_tem_entrega(): void
    {
        $this->actingAs(User::factory()->estoque()->create())->get('/separacao')
            ->assertOk()
            ->assertSee('Separação')
            ->assertSee('Devolução')
            ->assertDontSee('href="'.url('/entrega').'"', false);
    }

    public function test_uso_e_consumo_liberado_vai_direto_para_a_baixa(): void
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $workflow = app(RequisicaoWorkflow::class);
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        $workflow->aprovar($requisicao, $lider, null);
        $quantidades = $requisicao->itens->mapWithKeys(fn ($item) => [$item->id => (string) $item->qtd_solicitada])->all();
        $workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), $quantidades, null);

        $workflow->liberar($requisicao->fresh(), User::factory()->liderEstoque()->create(), null);

        $this->assertSame(StatusRequisicao::AGUARDANDO_BAIXA, $requisicao->fresh()->status);
    }

    public function test_separacao_do_uso_e_consumo_guarda_quem_vai_retirar(): void
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $workflow = app(RequisicaoWorkflow::class);
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        $workflow->aprovar($requisicao, $lider, null);
        $quantidades = $requisicao->itens->mapWithKeys(fn ($item) => [$item->id => (string) $item->qtd_solicitada])->all();

        $workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), $quantidades, null, 'Maria da Silva');

        $this->assertSame('Maria da Silva', $requisicao->fresh()->retirado_por_nome);
    }

    public function test_teste_continua_separando_e_entregando_na_mesma_tela(): void
    {
        $requisicao = Requisicao::factory()->status(StatusRequisicao::APROVADA)->create();
        $item = $requisicao->itens()->create(['descricao' => 'Mouse USB', 'unidade' => 'UN', 'qtd_solicitada' => 2]);

        $this->actingAs(User::factory()->estoque()->create())
            ->post("/requisicoes/{$requisicao->numero}/separar-e-entregar", [
                'itens' => [$item->id => '2'],
                'retirado_por_nome' => 'João Souza',
            ])->assertSessionHasNoErrors();

        $this->assertSame(StatusRequisicao::EM_POSSE, $requisicao->fresh()->status);
    }
}
