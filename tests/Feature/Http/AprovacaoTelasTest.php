<?php

namespace Tests\Feature\Http;

use App\Enums\StatusRequisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class AprovacaoTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
    }

    public function test_aprovador_ve_a_fila_do_setor_e_o_contador_no_menu(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $primeira = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $segunda = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->actingAs($lider)->get('/aprovacoes')
            ->assertOk()
            ->assertSeeInOrder([$primeira->numero, $segunda->numero])
            ->assertSee('data-contador-aprovacoes="2"', false);
    }

    public function test_quem_nao_aprova_nao_ve_o_menu_nem_a_fila(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/requisicoes')->assertOk()->assertDontSee('href="'.route('aprovacoes.index').'"', false);
        $this->actingAs($user)->get('/aprovacoes')->assertForbidden();
    }

    public function test_aprovar_pela_tela(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->actingAs($lider)->post("/requisicoes/{$requisicao->numero}/aprovar", ['senha' => 'password'])
            ->assertRedirect('/aprovacoes')
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    public function test_aprovar_com_senha_errada_reabre_o_modal_com_erro(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $url = "/requisicoes/{$requisicao->numero}";

        $this->actingAs($lider)->from($url)->post("{$url}/aprovar", ['senha' => 'errada', '_acao' => 'aprovar'])
            ->assertRedirect($url)
            ->assertSessionHasErrors('senha');

        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, $requisicao->fresh()->status);
        $this->get($url)->assertSee('Senha incorreta.')->assertSee('x-data="{ aberto: true', false);
    }

    public function test_reprovar_exige_motivo(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->actingAs($lider)->post("/requisicoes/{$requisicao->numero}/reprovar", ['senha' => 'password', 'motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($lider)->post("/requisicoes/{$requisicao->numero}/reprovar", ['senha' => 'password', 'motivo' => 'Já existe placa de teste disponível.'])
            ->assertRedirect('/aprovacoes');

        $this->assertSame(StatusRequisicao::REPROVADA, $requisicao->fresh()->status);
    }

    public function test_quem_nao_e_aprovador_recebe_403_ao_aprovar(): void
    {
        [$ti] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->actingAs(User::factory()->for($ti)->create())
            ->post("/requisicoes/{$requisicao->numero}/aprovar", ['senha' => 'password'])
            ->assertForbidden();
    }

    public function test_segundo_aprovador_ve_aviso_de_quem_ja_aprovou(): void
    {
        [$ti, $lider, $sublider] = $this->setorComAprovadores();
        $lider->forceFill(['nome' => 'Paloma Maclaine'])->save();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        app(RequisicaoWorkflow::class)->aprovar($requisicao, $lider, 'password');

        $this->actingAs($sublider)->from('/aprovacoes')
            ->post("/requisicoes/{$requisicao->numero}/aprovar", ['senha' => 'password'])
            ->assertRedirect('/aprovacoes')
            ->assertSessionHas('erro', fn (string $erro) => str_contains($erro, 'Paloma Maclaine'));
    }

    public function test_lider_do_estoque_ve_setor_sem_aprovador_com_o_motivo(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $requisicao = $this->abrirRequisicao(User::factory()->for($rh)->create());

        $this->actingAs(User::factory()->liderEstoque()->create())->get('/aprovacoes')
            ->assertOk()
            ->assertSee($requisicao->numero)
            ->assertSee('Setor sem aprovador cadastrado');
    }

    public function test_admin_que_nao_aprova_setor_nao_tem_fila(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/requisicoes')->assertOk()->assertDontSee('href="'.route('aprovacoes.index').'"', false);
        $this->actingAs($admin)->get('/aprovacoes')->assertForbidden();
    }

    public function test_detalhe_mostra_botoes_de_decisao_somente_para_quem_pode_aprovar(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $solicitante = User::factory()->for($ti)->create();
        $requisicao = $this->abrirRequisicao($solicitante);
        $url = "/requisicoes/{$requisicao->numero}";

        $this->actingAs($lider)->get($url)->assertSee('Assinar e aprovar');
        $this->actingAs($solicitante)->get($url)->assertDontSee('Assinar e aprovar')->assertSee('Cancelar requisição');
    }
}
