<?php

namespace Tests\Feature\Http;

use App\Enums\FormaPagamento;
use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class PedidoCompraTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
    }

    public function test_formulario_mostra_pedido_de_compra_motivo_e_forma_de_pagamento(): void
    {
        $this->actingAs(User::factory()->create())->get('/requisicoes/nova')
            ->assertOk()
            ->assertSee('Pedido de compra')
            ->assertSee('Motivo da compra')
            ->assertSee('Forma de pagamento')
            ->assertSee('À vista')
            ->assertSee('Parcelado')
            ->assertDontSee('Compra de funcionário')
            ->assertSee('Por que precisa comprar?');
    }

    public function test_abrir_pedido_de_compra_sem_forma_de_pagamento_volta_com_erro(): void
    {
        $dados = $this->dadosRequisicao(['tipo' => 'COMPRA_FUNCIONARIO', 'data_prevista_devolucao' => null]);

        $this->actingAs(User::factory()->create())->post('/requisicoes', $dados)
            ->assertSessionHasErrors('forma_pagamento');
    }

    public function test_abrir_pedido_de_compra_pela_tela_grava_a_forma_de_pagamento(): void
    {
        $dados = $this->dadosRequisicao([
            'tipo' => 'COMPRA_FUNCIONARIO',
            'forma_pagamento' => 'PARCELADO',
            'data_prevista_devolucao' => null,
        ]);

        $this->actingAs(User::factory()->create())->post('/requisicoes', $dados)->assertSessionHasNoErrors();

        $this->assertSame(FormaPagamento::PARCELADO, Requisicao::query()->latest('id')->firstOrFail()->forma_pagamento);
    }

    public function test_responsavel_ve_a_forma_pedida_e_o_campo_de_parcelas(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create(['forma_pagamento' => FormaPagamento::PARCELADO]);

        $this->actingAs($erica)->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee('Forma de pagamento')
            ->assertSee('Número de parcelas')
            ->assertSee('O solicitante pediu parcelado');
    }

    public function test_responsavel_aprova_a_compra_parcelada_pela_tela(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create(['forma_pagamento' => FormaPagamento::A_VISTA]);

        $this->actingAs($erica)->post(route('requisicoes.decidir-compra', $requisicao), [
            'decisao' => 'aprovar',
            'forma_pagamento' => 'PARCELADO',
            'parcelas' => '5',
        ])->assertSessionHasNoErrors();

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::COMPRA_APROVADA, $requisicao->status);
        $this->assertSame(FormaPagamento::PARCELADO, $requisicao->forma_pagamento);
        $this->assertSame(5, $requisicao->parcelas);
    }

    public function test_aprovar_parcelado_sem_parcelas_volta_com_erro(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create(['forma_pagamento' => FormaPagamento::PARCELADO]);

        $this->actingAs($erica)->post(route('requisicoes.decidir-compra', $requisicao), [
            'decisao' => 'aprovar',
            'forma_pagamento' => 'PARCELADO',
        ])->assertSessionHasErrors('parcelas');

        $this->assertSame(StatusRequisicao::AGUARDANDO_COMPRA, $requisicao->fresh()->status);
    }

    public function test_menu_e_fila_usam_o_nome_novo(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();

        $this->actingAs($erica)->get(route('compras.index'))
            ->assertOk()
            ->assertSee('Pedidos de compra')
            ->assertDontSee('Compras de funcionários');
    }
}
