<?php

namespace Tests\Feature\Http;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class DecisaoPorItemTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
    }

    /**
     * @return array{0: Requisicao, 1: User}
     */
    private function aguardandoAprovacao(array $sobrescrever = []): array
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create(), $sobrescrever + ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);

        return [$requisicao, $lider];
    }

    public function test_detalhe_com_varios_itens_oferece_decisao_por_item(): void
    {
        [$requisicao, $lider] = $this->aguardandoAprovacao();
        $item = $requisicao->itens->first();

        $this->actingAs($lider)->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee('Decisão por item')
            ->assertSee("itens[{$item->id}][decisao]", false)
            ->assertSee("itens[{$item->id}][motivo]", false);
    }

    public function test_detalhe_com_um_item_nao_mostra_decisao_por_item(): void
    {
        [$requisicao, $lider] = $this->aguardandoAprovacao([
            'itens' => [['descricao' => 'Cabo HDMI 2.1 2m', 'unidade' => 'UN', 'quantidade' => '2']],
        ]);

        $this->actingAs($lider)->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertDontSee('Decisão por item');
    }

    public function test_aprovar_recusando_um_item_pela_tela(): void
    {
        [$requisicao, $lider] = $this->aguardandoAprovacao();
        [$primeiro, $segundo] = $requisicao->itens;

        $this->actingAs($lider)->post("/requisicoes/{$requisicao->numero}/aprovar", [
            'itens' => [
                $primeiro->id => ['decisao' => 'aprovar', 'motivo' => ''],
                $segundo->id => ['decisao' => 'recusar', 'motivo' => 'Item em falta no estoque'],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
        $this->assertNull($primeiro->fresh()->recusado_em);
        $this->assertSame('Item em falta no estoque', $segundo->fresh()->motivo_recusa);

        $this->actingAs($lider)->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee('Recusado por')
            ->assertSee('Item em falta no estoque')
            ->assertSee('Assinaturas íntegras')
            ->assertDontSee('Divergente');
        $this->actingAs($lider)->get(route('requisicoes.pdf', $requisicao))->assertOk();
    }

    public function test_recusar_todos_os_itens_pela_tela_reprova(): void
    {
        [$requisicao, $lider] = $this->aguardandoAprovacao();
        $decisoes = $requisicao->itens->mapWithKeys(fn ($item) => [$item->id => ['decisao' => 'recusar', 'motivo' => 'Não precisa mais']])->all();

        $this->actingAs($lider)->post("/requisicoes/{$requisicao->numero}/aprovar", ['itens' => $decisoes])
            ->assertSessionHas('sucesso', fn (string $msg) => str_contains($msg, 'reprovada'));

        $this->assertSame(StatusRequisicao::REPROVADA, $requisicao->fresh()->status);
    }

    public function test_recusar_item_sem_motivo_volta_com_erro_no_item(): void
    {
        [$requisicao, $lider] = $this->aguardandoAprovacao();
        $segundo = $requisicao->itens->last();

        $this->actingAs($lider)->post("/requisicoes/{$requisicao->numero}/aprovar", [
            'itens' => [$segundo->id => ['decisao' => 'recusar', 'motivo' => '']],
        ])->assertSessionHasErrors("itens.{$segundo->id}.motivo");

        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, $requisicao->fresh()->status);
    }

    public function test_responsavel_aprova_a_compra_recusando_um_item_pela_tela(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create();
        $a = $requisicao->itens()->create(['descricao' => 'SSD NVMe 1TB', 'unidade' => 'UN', 'qtd_solicitada' => 1]);
        $b = $requisicao->itens()->create(['descricao' => 'Memória DDR4 16GB', 'unidade' => 'UN', 'qtd_solicitada' => 2]);

        $this->actingAs($erica)->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee('Decisão por item')
            ->assertSee("itens[{$b->id}][decisao]", false);

        $this->actingAs($erica)->post(route('requisicoes.decidir-compra', $requisicao), [
            'decisao' => 'aprovar',
            'forma_pagamento' => 'A_VISTA',
            'itens' => [
                $a->id => ['decisao' => 'aprovar'],
                $b->id => ['decisao' => 'recusar', 'motivo' => 'Já temos em estoque'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(StatusRequisicao::COMPRA_APROVADA, $requisicao->fresh()->status);
        $this->assertSame('Já temos em estoque', $b->fresh()->motivo_recusa);
    }

    public function test_separacao_so_lista_os_itens_aprovados(): void
    {
        [$requisicao, $lider] = $this->aguardandoAprovacao();
        [$primeiro, $segundo] = $requisicao->itens;
        $this->actingAs($lider)->post("/requisicoes/{$requisicao->numero}/aprovar", [
            'itens' => [$segundo->id => ['decisao' => 'recusar', 'motivo' => 'Item em falta no estoque']],
        ]);

        $this->actingAs(User::factory()->estoque()->create())->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee("name=\"itens[{$primeiro->id}]\"", false)
            ->assertDontSee("name=\"itens[{$segundo->id}]\"", false);
    }
}
