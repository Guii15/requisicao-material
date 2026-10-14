<?php

namespace Tests\Feature\Http;

use App\Models\RequisicaoEvento;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class PainelTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-09 10:00:00');
    }

    public function test_filtro_de_mes_mostra_so_a_atividade_daquele_mes(): void
    {
        [$setor] = $this->setorComAprovadores();
        $solicitante = User::factory()->for($setor)->create();

        $this->travelTo('2026-09-24 10:00:00');
        $antiga = $this->abrirRequisicao($solicitante);

        $this->travelTo('2026-10-09 10:00:00');
        $recente = $this->abrirRequisicao($solicitante, ['data_prevista_devolucao' => '2026-10-12']);

        $this->actingAs($solicitante)->get('/painel?mes=2026-09')
            ->assertOk()
            ->assertSee($antiga->numero)
            ->assertDontSee($recente->numero);

        $this->actingAs($solicitante)->get('/painel')
            ->assertOk()
            ->assertSee($recente->numero)
            ->assertSee($antiga->numero);
    }

    public function test_mes_fora_do_formato_e_ignorado(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/painel?mes=qualquer-coisa')->assertOk();
        $this->assertSame(0, RequisicaoEvento::count());
    }

    public function test_filtro_oferece_os_ultimos_doze_meses(): void
    {
        $this->actingAs(User::factory()->create())->get('/painel')
            ->assertOk()
            ->assertSee('Outubro de 2026')
            ->assertSee('Novembro de 2025');
    }

    public function test_cada_requisicao_aparece_uma_vez_mesmo_com_varias_alteracoes(): void
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        app(RequisicaoWorkflow::class)->aprovar($requisicao, $lider, null);
        $q = $requisicao->fresh()->itens->mapWithKeys(fn ($i) => [$i->id => (string) $i->qtd_solicitada])->all();
        app(RequisicaoWorkflow::class)->separar($requisicao->fresh(), User::factory()->estoque()->create(), $q, null);

        $resposta = $this->actingAs($lider)->get('/painel')->assertOk();

        $this->assertSame(1, $resposta->viewData('historico')->total());
    }

    public function test_requisicao_que_muda_sobe_para_o_topo(): void
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $solicitante = User::factory()->for($setor)->create();
        $primeira = $this->abrirRequisicao($solicitante, ['data_prevista_devolucao' => '2026-10-12']);
        $this->travelTo('2026-10-09 11:00:00');
        $segunda = $this->abrirRequisicao($solicitante, ['data_prevista_devolucao' => '2026-10-12']);

        $antes = $this->actingAs($lider)->get('/painel')->viewData('historico')->pluck('numero')->all();
        $this->assertSame([$segunda->numero, $primeira->numero], $antes);

        $this->travelTo('2026-10-09 12:00:00');
        app(RequisicaoWorkflow::class)->aprovar($primeira, $lider, null);

        $depois = $this->actingAs($lider)->get('/painel')->viewData('historico')->pluck('numero')->all();
        $this->assertSame([$primeira->numero, $segunda->numero], $depois);
    }

    public function test_linha_mostra_a_situacao_atual_e_quem_fez_a_ultima_alteracao(): void
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create(), ['data_prevista_devolucao' => '2026-10-12']);
        app(RequisicaoWorkflow::class)->aprovar($requisicao, $lider, null);

        $this->actingAs($lider)->get('/painel')
            ->assertOk()
            ->assertSee($requisicao->numero)
            ->assertSee('Aprovada')
            ->assertSee($lider->nome);
    }

    public function test_painel_nao_mostra_avanco_automatico_como_atividade(): void
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        app(RequisicaoWorkflow::class)->aprovar($requisicao, $lider, null);
        $q = $requisicao->fresh()->itens->mapWithKeys(fn ($i) => [$i->id => (string) $i->qtd_solicitada])->all();
        app(RequisicaoWorkflow::class)->separar($requisicao->fresh(), User::factory()->estoque()->create(), $q, null);

        $this->actingAs($lider)->get('/painel')->assertOk()->assertDontSee('Avançou automaticamente');
    }

    public function test_filtro_por_setor_mostra_so_requisicoes_do_setor(): void
    {
        [$ti, $liderTi] = $this->setorComAprovadores('TI');
        [$rh] = $this->setorComAprovadores('RH');
        $doTi = $this->abrirRequisicao(User::factory()->for($ti)->create(), ['data_prevista_devolucao' => '2026-10-12']);
        $doRh = $this->abrirRequisicao(User::factory()->for($rh)->create(), ['data_prevista_devolucao' => '2026-10-12']);

        $numeros = $this->actingAs($liderTi)->get('/painel?setor_id='.$ti->id)->viewData('historico')->pluck('numero')->all();

        $this->assertSame([$doTi->numero], $numeros);
        $this->assertNotContains($doRh->numero, $numeros);
    }
}
