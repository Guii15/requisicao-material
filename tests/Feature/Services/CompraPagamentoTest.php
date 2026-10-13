<?php

namespace Tests\Feature\Services;

use App\Enums\FormaPagamento;
use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\FilaDeCompras;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class CompraPagamentoTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private RequisicaoWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->workflow = app(RequisicaoWorkflow::class);
    }

    private function dadosCompra(array $sobrescrever = []): array
    {
        return $this->dadosRequisicao(array_merge([
            'tipo' => 'COMPRA_FUNCIONARIO',
            'forma_pagamento' => 'A_VISTA',
            'data_prevista_devolucao' => null,
        ], $sobrescrever));
    }

    private function compraAguardando(array $atributos = []): Requisicao
    {
        return Requisicao::factory()->compra()->create($atributos);
    }

    public function test_pedido_de_compra_guarda_a_forma_de_pagamento_escolhida_por_quem_pede(): void
    {
        $solicitante = User::factory()->create();

        $requisicao = $this->workflow->criar($solicitante, $this->dadosCompra(['forma_pagamento' => 'PARCELADO']));

        $this->assertSame(TipoRequisicao::COMPRA_FUNCIONARIO, $requisicao->tipo);
        $this->assertSame(FormaPagamento::PARCELADO, $requisicao->fresh()->forma_pagamento);
        $this->assertNull($requisicao->fresh()->parcelas);
    }

    public function test_pedido_de_compra_exige_a_forma_de_pagamento(): void
    {
        $this->expectException(ValidationException::class);

        $this->workflow->criar(User::factory()->create(), $this->dadosCompra(['forma_pagamento' => '']));
    }

    public function test_pedido_de_compra_recusa_forma_de_pagamento_desconhecida(): void
    {
        $this->expectException(ValidationException::class);

        $this->workflow->criar(User::factory()->create(), $this->dadosCompra(['forma_pagamento' => 'BOLETO']));
    }

    public function test_outros_tipos_ignoram_a_forma_de_pagamento(): void
    {
        $requisicao = $this->workflow->criar(User::factory()->create(), $this->dadosRequisicao(['forma_pagamento' => 'PARCELADO']));

        $this->assertNull($requisicao->fresh()->forma_pagamento);
    }

    public function test_responsavel_aprova_a_vista_sem_parcelas(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = $this->compraAguardando(['forma_pagamento' => FormaPagamento::A_VISTA]);

        $this->workflow->decidirCompra($requisicao, $erica, true, null);

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::COMPRA_APROVADA, $requisicao->status);
        $this->assertSame(FormaPagamento::A_VISTA, $requisicao->forma_pagamento);
        $this->assertNull($requisicao->parcelas);
    }

    public function test_parcelado_exige_numero_de_parcelas_ao_aprovar(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = $this->compraAguardando(['forma_pagamento' => FormaPagamento::PARCELADO]);

        try {
            $this->workflow->decidirCompra($requisicao, $erica, true, null);
            $this->fail('Devia exigir as parcelas.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parcelas', $e->errors());
        }

        $this->assertSame(StatusRequisicao::AGUARDANDO_COMPRA, $requisicao->fresh()->status);
    }

    public function test_parcelas_precisam_estar_entre_2_e_24(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();

        foreach ([1, 25, 0, -3] as $invalida) {
            $requisicao = $this->compraAguardando(['forma_pagamento' => FormaPagamento::PARCELADO]);

            try {
                $this->workflow->decidirCompra($requisicao, $erica, true, null, 'PARCELADO', $invalida);
                $this->fail("Aceitou {$invalida} parcelas.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('parcelas', $e->errors(), "parcelas={$invalida}");
            }
        }
    }

    public function test_responsavel_grava_as_parcelas_ao_aprovar(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = $this->compraAguardando(['forma_pagamento' => FormaPagamento::PARCELADO]);

        $this->workflow->decidirCompra($requisicao, $erica, true, null, 'PARCELADO', 6);

        $requisicao->refresh();
        $this->assertSame(FormaPagamento::PARCELADO, $requisicao->forma_pagamento);
        $this->assertSame(6, $requisicao->parcelas);
    }

    public function test_responsavel_pode_trocar_a_forma_de_pagamento_pedida(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = $this->compraAguardando(['forma_pagamento' => FormaPagamento::A_VISTA]);

        $this->workflow->decidirCompra($requisicao, $erica, true, null, 'PARCELADO', 3);

        $requisicao->refresh();
        $this->assertSame(FormaPagamento::PARCELADO, $requisicao->forma_pagamento);
        $this->assertSame(3, $requisicao->parcelas);
    }

    public function test_trocar_para_a_vista_limpa_as_parcelas(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = $this->compraAguardando(['forma_pagamento' => FormaPagamento::PARCELADO, 'parcelas' => 4]);

        $this->workflow->decidirCompra($requisicao, $erica, true, null, 'A_VISTA', 4);

        $requisicao->refresh();
        $this->assertSame(FormaPagamento::A_VISTA, $requisicao->forma_pagamento);
        $this->assertNull($requisicao->parcelas);
    }

    public function test_reprovar_nao_exige_forma_nem_parcelas(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = $this->compraAguardando(['forma_pagamento' => FormaPagamento::PARCELADO]);

        $this->workflow->decidirCompra($requisicao, $erica, false, 'Fornecedor sem estoque.');

        $this->assertSame(StatusRequisicao::COMPRA_REPROVADA, $requisicao->fresh()->status);
    }

    public function test_responsavel_decide_a_propria_compra(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = $this->compraAguardando([
            'solicitante_id' => $erica->id,
            'forma_pagamento' => FormaPagamento::A_VISTA,
        ]);

        $this->assertTrue($erica->can('decidirCompra', $requisicao));

        $this->workflow->decidirCompra($requisicao, $erica, true, null);

        $this->assertSame(StatusRequisicao::COMPRA_APROVADA, $requisicao->fresh()->status);
    }

    public function test_a_compra_da_responsavel_aparece_na_fila_dela(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $propria = $this->compraAguardando(['solicitante_id' => $erica->id]);
        $outra = $this->compraAguardando();

        $numeros = app(FilaDeCompras::class)->para($erica)->pluck('numero')->all();

        $this->assertEqualsCanonicalizing([$propria->numero, $outra->numero], $numeros);
    }
}
