<?php

namespace Tests\Feature\Http;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class SeparacaoTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
    }

    private function requisicaoAprovada(): Requisicao
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        app(RequisicaoWorkflow::class)->aprovar($requisicao, $lider, 'password');

        return $requisicao->fresh();
    }

    public function test_estoque_ve_a_fila_e_o_contador_no_menu(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();

        $this->actingAs($estoquista)->get('/separacao')
            ->assertOk()
            ->assertSee($requisicao->numero)
            ->assertSee('data-contador-separacao="1"', false);
    }

    public function test_quem_nao_e_do_estoque_nao_ve_o_menu_nem_a_fila(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/requisicoes')->assertOk()->assertDontSee('href="'.route('separacao.index').'"', false);
        $this->actingAs($user)->get('/separacao')->assertForbidden();
    }

    public function test_separar_pela_tela(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();
        $itens = $requisicao->itens->pluck('qtd_solicitada', 'id')->all();

        $this->actingAs($estoquista)->post("/requisicoes/{$requisicao->numero}/separar", [
            'itens' => $itens,
            'senha' => 'password',
        ])
            ->assertRedirect('/separacao')
            ->assertSessionHas('sucesso');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE, $requisicao->status);
        $this->assertSame($estoquista->id, $requisicao->separado_por_id);
    }

    public function test_teste_nao_se_separa_sozinho_so_separa_e_entrega(): void
    {
        $requisicao = Requisicao::factory()->status(StatusRequisicao::APROVADA)->create();
        $item = $requisicao->itens()->create(['descricao' => 'Mouse USB', 'unidade' => 'UN', 'qtd_solicitada' => 1]);

        $this->actingAs(User::factory()->estoque()->create())
            ->post("/requisicoes/{$requisicao->numero}/separar", ['itens' => [$item->id => '1']])
            ->assertStatus(422);

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    public function test_separar_com_quantidade_maior_reabre_o_modal_com_erro(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();
        $item = $requisicao->itens->first();
        $url = "/requisicoes/{$requisicao->numero}";

        $this->actingAs($estoquista)->from($url)->post("{$url}/separar", [
            '_acao' => 'separar',
            'itens' => [$item->id => '999'],
            'senha' => 'password',
        ])
            ->assertRedirect($url)
            ->assertSessionHasErrors("itens.{$item->id}");

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    public function test_quem_nao_e_do_estoque_recebe_403_ao_separar(): void
    {
        $requisicao = $this->requisicaoAprovada();

        $this->actingAs(User::factory()->create())
            ->post("/requisicoes/{$requisicao->numero}/separar", ['itens' => [], 'senha' => 'password'])
            ->assertForbidden();
    }

    public function test_detalhe_mostra_botao_separar_somente_para_quem_pode(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();
        $url = "/requisicoes/{$requisicao->numero}";

        $this->actingAs($estoquista)->get($url)->assertSee('Separar');
        $this->actingAs($requisicao->solicitante)->get($url)->assertDontSee('>Separar<', false);
    }
}
