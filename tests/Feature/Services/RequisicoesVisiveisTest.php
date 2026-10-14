<?php

namespace Tests\Feature\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use App\Services\RequisicoesVisiveis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * A consulta tem que bater com a RequisicaoPolicy::view: o painel não pode mostrar
 * uma requisição que a pessoa não consegue abrir, nem esconder uma que ela consegue.
 */
class RequisicoesVisiveisTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    public function test_mostra_exatamente_as_requisicoes_que_a_policy_deixa_ver(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        [$ti, $liderTi] = $this->setorComAprovadores('TI');
        [$rh] = $this->setorComAprovadores('RH');
        $solicitante = User::factory()->for($ti)->create();
        $colega = User::factory()->for($ti)->create();
        $doRh = User::factory()->for($rh)->create();

        // Uma de cada tipo em vários pontos do fluxo, de dois setores.
        foreach (StatusRequisicao::cases() as $status) {
            Requisicao::factory()->status($status)->create(['solicitante_id' => $solicitante->id]);
            Requisicao::factory()->usoConsumo()->status($status)->create(['solicitante_id' => $doRh->id]);
        }
        foreach ([StatusRequisicao::AGUARDANDO_APROVACAO, StatusRequisicao::AGUARDANDO_COMPRA, StatusRequisicao::COMPRA_APROVADA, StatusRequisicao::REPROVADA] as $status) {
            Requisicao::factory()->compra()->status($status)->create(['solicitante_id' => $colega->id]);
        }

        // Assinada pelo líder do TI: ele continua vendo mesmo depois de sair da fila dele.
        $assinada = $this->abrirRequisicao($solicitante);
        app(RequisicaoWorkflow::class)->aprovar($assinada, $liderTi, null);

        $pessoas = [
            'solicitante' => $solicitante,
            'colega' => $colega,
            'do RH' => $doRh,
            'líder do TI' => $liderTi,
            'estoque' => User::factory()->estoque()->create(),
            'líder do estoque' => User::factory()->liderEstoque()->create(),
            'responsável pela baixa' => User::factory()->responsavelBaixa()->create(),
            'aprova compras' => User::factory()->create(['aprova_compras' => true]),
            'admin' => User::factory()->admin()->create(),
        ];

        foreach ($pessoas as $quem => $pessoa) {
            $esperado = Requisicao::all()->filter(fn (Requisicao $r) => $pessoa->can('view', $r))->pluck('id')->sort()->values()->all();
            $visiveis = app(RequisicoesVisiveis::class)->para($pessoa)->pluck('requisicoes.id')->sort()->values()->all();

            $this->assertSame($esperado, $visiveis, "Diferença para: {$quem}");
        }
    }

    public function test_colega_nao_ve_a_compra_de_outro_no_painel(): void
    {
        $this->travelTo('2026-10-09 10:00:00');
        [$ti] = $this->setorComAprovadores('TI');
        $comprador = User::factory()->for($ti)->create();
        $colega = User::factory()->for($ti)->create();
        $compra = $this->abrirRequisicao($comprador, [
            'tipo' => TipoRequisicao::COMPRA_FUNCIONARIO->value,
            'data_prevista_devolucao' => null,
            'forma_pagamento' => 'A_VISTA',
            'itens' => [['descricao' => 'Notebook pessoal', 'unidade' => 'UN', 'quantidade' => '1']],
        ]);

        $this->actingAs($colega)->get('/painel')->assertOk()->assertDontSee($compra->numero)->assertDontSee('Notebook pessoal');
        $this->actingAs($comprador)->get('/painel')->assertOk()->assertSee($compra->numero);
    }
}
