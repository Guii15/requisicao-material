<?php

namespace Tests\Feature\Http;

use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\AssinaturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class SetorDeDestinoTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private Setor $estoque;

    private User $solicitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->estoque = Setor::factory()->create(['nome' => 'ESTOQUE']);
        [$setor] = $this->setorComAprovadores();
        $this->solicitante = User::factory()->for($setor)->create();
    }

    private function enviar(array $dados): Requisicao
    {
        $this->actingAs($this->solicitante)->post('/requisicoes', $this->dadosRequisicao($dados))->assertSessionHasNoErrors();

        return Requisicao::query()->latest('id')->firstOrFail();
    }

    public function test_tela_de_nova_requisicao_oferece_os_setores_com_o_estoque_marcado(): void
    {
        Setor::factory()->create(['nome' => 'SHOWROOM']);
        Setor::factory()->create(['nome' => 'DESATIVADO', 'ativo' => false]);

        $this->actingAs($this->solicitante)->get('/requisicoes/nova')
            ->assertOk()
            ->assertSee('Pedido para o setor')
            ->assertSee('>Showroom</option>', false)
            ->assertDontSee('Desativado')
            ->assertSee('\u0022destino\u0022:\u0022'.$this->estoque->id.'\u0022', false);
    }

    public function test_sem_escolher_o_destino_e_o_estoque(): void
    {
        $this->assertSame($this->estoque->id, $this->enviar([])->setor_destino_id);
    }

    public function test_pode_escolher_outro_setor_e_ele_aparece_na_requisicao_e_no_pdf(): void
    {
        $showroom = Setor::factory()->create(['nome' => 'SHOWROOM']);
        $requisicao = $this->enviar(['setor_destino_id' => (string) $showroom->id]);

        $this->assertSame($showroom->id, $requisicao->setor_destino_id);
        $this->actingAs($this->solicitante)->get("/requisicoes/{$requisicao->numero}")->assertOk()->assertSee('Pedido para')->assertSee('SHOWROOM');
        $this->actingAs($this->solicitante)->get("/requisicoes/{$requisicao->numero}/pdf")->assertOk();
    }

    public function test_setor_inexistente_ou_desativado_e_recusado(): void
    {
        $inativo = Setor::factory()->create(['nome' => 'VELHO', 'ativo' => false]);

        foreach ([9999, $inativo->id, 'abc'] as $valor) {
            $this->actingAs($this->solicitante)->post('/requisicoes', $this->dadosRequisicao(['setor_destino_id' => $valor]))
                ->assertSessionHasErrors('setor_destino_id');
        }

        $this->assertSame(0, Requisicao::count());
    }

    public function test_pedido_de_compra_nao_tem_setor_de_destino(): void
    {
        $requisicao = $this->enviar([
            'tipo' => 'COMPRA_FUNCIONARIO',
            'forma_pagamento' => 'A_VISTA',
            'data_prevista_devolucao' => null,
            'setor_destino_id' => (string) $this->estoque->id,
        ]);

        $this->assertNull($requisicao->setor_destino_id);
    }

    public function test_a_assinatura_cobre_o_setor_de_destino(): void
    {
        $showroom = Setor::factory()->create(['nome' => 'SHOWROOM']);
        $requisicao = $this->enviar(['setor_destino_id' => (string) $showroom->id]);

        $this->assertTrue(app(AssinaturaService::class)->verificar($requisicao)->integro);
    }
}
