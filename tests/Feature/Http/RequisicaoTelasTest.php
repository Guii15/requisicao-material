<?php

namespace Tests\Feature\Http;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class RequisicaoTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
    }

    public function test_inicio_leva_para_minhas_requisicoes(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect('/requisicoes');
    }

    public function test_minhas_requisicoes_lista_somente_as_do_usuario(): void
    {
        $eu = User::factory()->create();
        $minha = $this->abrirRequisicao($eu);
        $deOutro = $this->abrirRequisicao(User::factory()->create());

        $this->actingAs($eu)->get('/requisicoes')
            ->assertOk()
            ->assertSee($minha->numero)
            ->assertSee('Placa de vídeo RTX 4060 8GB')
            ->assertDontSee($deOutro->numero);
    }

    public function test_minhas_requisicoes_vazia_orienta_o_usuario(): void
    {
        $this->actingAs(User::factory()->create())->get('/requisicoes')
            ->assertOk()
            ->assertSee('Você ainda não abriu nenhuma requisição')
            ->assertSee('é por aqui que você pede');
    }

    public function test_tela_de_nova_requisicao_mostra_o_prazo_de_devolucao(): void
    {
        $this->actingAs(User::factory()->create())->get('/requisicoes/nova')
            ->assertOk()
            ->assertSee('USO E CONSUMO')
            ->assertSee('29/09/2026');
    }

    public function test_abrir_requisicao_pela_tela(): void
    {
        $eu = User::factory()->create();

        $this->actingAs($eu)->post('/requisicoes', $this->dadosRequisicao(['senha' => 'password']))
            ->assertRedirect('/requisicoes/REQ-2026-000001')
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, Requisicao::firstOrFail()->status);
    }

    public function test_abrir_com_senha_errada_volta_com_erro_mantendo_o_que_foi_digitado(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/requisicoes/nova')
            ->post('/requisicoes', $this->dadosRequisicao(['senha' => 'errada']))
            ->assertRedirect('/requisicoes/nova')
            ->assertSessionHasErrors('senha');

        $this->assertSame(0, Requisicao::count());
        $this->assertSame('Validar a placa no PC do pedido 88412.', session()->getOldInput('finalidade'));
        $this->assertNull(session()->getOldInput('senha'));
    }

    public function test_detalhe_mostra_itens_assinaturas_e_integridade(): void
    {
        $eu = User::factory()->create(['nome' => 'Kevelin Honorato']);
        $requisicao = $this->abrirRequisicao($eu);

        $this->actingAs($eu)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Placa de vídeo RTX 4060 8GB')
            ->assertSee('Solicitado por')
            ->assertSee('Kevelin Honorato')
            ->assertSee('Assinaturas íntegras');
    }

    public function test_detalhe_mostra_pelo_nome_quem_pode_aprovar(): void
    {
        [$ti, $lider, $sublider] = $this->setorComAprovadores();
        $lider->forceFill(['nome' => 'Leandro Moreira'])->save();
        $sublider->forceFill(['nome' => 'Juliana Duarte'])->save();
        $eu = User::factory()->for($ti)->create();
        $requisicao = $this->abrirRequisicao($eu);

        $this->actingAs($eu)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Aguardando Juliana Duarte ou Leandro Moreira');
    }

    public function test_historico_nao_diz_setor_quando_quem_aprovou_foi_o_estoque(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $eu = User::factory()->for($rh)->create();
        $requisicao = $this->abrirRequisicao($eu);
        app(RequisicaoWorkflow::class)->aprovar($requisicao, User::factory()->liderEstoque()->create(), 'password');

        $this->actingAs($eu)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertDontSee('Aprovada pelo setor');
    }

    public function test_estoque_nao_abre_requisicao_nao_aprovada_pelo_link_direto(): void
    {
        $requisicao = $this->abrirRequisicao(User::factory()->create());

        $this->actingAs(User::factory()->estoque()->create())
            ->get("/requisicoes/{$requisicao->numero}")
            ->assertForbidden();
    }

    public function test_usuario_nao_abre_requisicao_de_outra_pessoa(): void
    {
        $requisicao = $this->abrirRequisicao(User::factory()->create());

        $this->actingAs(User::factory()->create())->get("/requisicoes/{$requisicao->numero}")->assertForbidden();
    }

    public function test_solicitante_cancela_pela_tela_e_volta_para_a_lista(): void
    {
        $eu = User::factory()->create();
        $requisicao = $this->abrirRequisicao($eu);

        $this->actingAs($eu)->post("/requisicoes/{$requisicao->numero}/cancelar", ['motivo' => 'Pedi o item errado.'])
            ->assertRedirect('/requisicoes')
            ->assertSessionHas('sucesso', "Requisição {$requisicao->numero} cancelada.");

        $this->assertSame(StatusRequisicao::CANCELADA, $requisicao->fresh()->status);
    }

    public function test_detalhe_da_propria_requisicao_volta_para_minhas_requisicoes(): void
    {
        $eu = User::factory()->create();
        $requisicao = $this->abrirRequisicao($eu);

        $this->actingAs($eu)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('href="'.route('requisicoes.index').'" data-voltar', false)
            ->assertSee('Voltar para Minhas requisições');
    }

    public function test_detalhe_aberto_pelo_aprovador_volta_para_aprovacoes(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->actingAs($lider)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('href="'.route('aprovacoes.index').'" data-voltar', false)
            ->assertSee('Voltar para Aprovações');
    }

    public function test_aprovador_que_cancela_pedido_proprio_volta_para_minhas_requisicoes(): void
    {
        [, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao($lider);

        $this->actingAs($lider)->post("/requisicoes/{$requisicao->numero}/cancelar", ['motivo' => 'Não preciso mais.'])
            ->assertRedirect('/requisicoes');
    }

    public function test_cancelar_sem_motivo_volta_com_erro(): void
    {
        $eu = User::factory()->create();
        $requisicao = $this->abrirRequisicao($eu);

        $this->actingAs($eu)->post("/requisicoes/{$requisicao->numero}/cancelar", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, $requisicao->fresh()->status);
    }

    public function test_cancelar_requisicao_alheia_e_proibido(): void
    {
        $requisicao = $this->abrirRequisicao(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post("/requisicoes/{$requisicao->numero}/cancelar", ['motivo' => 'Tentativa indevida'])
            ->assertForbidden();
    }
}
