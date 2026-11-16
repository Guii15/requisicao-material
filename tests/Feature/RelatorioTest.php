<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\RequisicaoItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioTest extends TestCase
{
    use RefreshDatabase;

    public function test_so_responsavel_pela_baixa_e_admin_acessam(): void
    {
        $this->actingAs(User::factory()->create())->get('/relatorios')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/relatorios/exportar')->assertForbidden();
        $this->actingAs(User::factory()->responsavelBaixa()->create())->get('/relatorios')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/relatorios')->assertOk();
    }

    public function test_lista_uso_e_consumo_por_item_e_separa_por_tipo(): void
    {
        $uso = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::BAIXADA)->create(['numero' => 'REQ-2026-000101']);
        RequisicaoItem::factory()->for($uso)->create(['descricao' => 'Fita isolante']);
        RequisicaoItem::factory()->for($uso)->create(['descricao' => 'Abraçadeira']);
        $compra = Requisicao::factory()->compra()->create(['numero' => 'REQ-2026-000102']);
        RequisicaoItem::factory()->for($compra)->create(['descricao' => 'Fone de ouvido']);

        $meire = User::factory()->responsavelBaixa()->create();

        $this->actingAs($meire)->get('/relatorios?tipo=USO_CONSUMO')
            ->assertOk()
            ->assertSee('Fita isolante')
            ->assertSee('Abraçadeira')
            ->assertSee('Baixada')
            ->assertDontSee('Fone de ouvido')
            ->assertSee('1 requisição, 2 itens', false);

        $this->actingAs($meire)->get('/relatorios?tipo=COMPRA_FUNCIONARIO')
            ->assertOk()
            ->assertSee('Fone de ouvido')
            ->assertDontSee('Fita isolante');
    }

    public function test_filtra_por_periodo_e_situacao(): void
    {
        $antiga = Requisicao::factory()->usoConsumo()->create(['created_at' => now()->subMonths(2)]);
        RequisicaoItem::factory()->for($antiga)->create(['descricao' => 'Item antigo']);
        $aguardando = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::AGUARDANDO_BAIXA)->create();
        RequisicaoItem::factory()->for($aguardando)->create(['descricao' => 'Item aguardando']);
        $baixada = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::BAIXADA)->create();
        RequisicaoItem::factory()->for($baixada)->create(['descricao' => 'Item baixado']);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/relatorios?tipo=USO_CONSUMO')
            ->assertSee('Item aguardando')->assertSee('Item baixado')->assertDontSee('Item antigo');

        $this->actingAs($admin)->get('/relatorios?tipo=USO_CONSUMO&status=BAIXADA')
            ->assertSee('Item baixado')->assertDontSee('Item aguardando');

        $this->actingAs($admin)->get('/relatorios?tipo=USO_CONSUMO&de='.now()->subMonths(3)->format('Y-m-d'))
            ->assertSee('Item antigo');
    }

    public function test_baixa_para_o_excel_com_as_colunas_do_tipo(): void
    {
        $compra = Requisicao::factory()->compra()->status(StatusRequisicao::COMPRA_APROVADA)->create([
            'numero' => 'REQ-2026-000200',
            'forma_pagamento' => FormaPagamento::PARCELADO,
            'parcelas' => 3,
        ]);
        RequisicaoItem::factory()->for($compra)->create(['descricao' => 'Teclado', 'qtd_solicitada' => 2]);

        $resposta = $this->actingAs(User::factory()->responsavelBaixa()->create())
            ->get('/relatorios/exportar?tipo=COMPRA_FUNCIONARIO');

        $resposta->assertOk();
        $this->assertStringContainsString('relatorio-pedido-de-compra', $resposta->headers->get('content-disposition'));
        $csv = $resposta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Nº;"Data do pedido";Solicitante;Setor;Código;Produto;Unid.;"Qtd. pedida";Pagamento', $csv);
        $this->assertStringContainsString('REQ-2026-000200', $csv);
        $this->assertStringContainsString('Teclado', $csv);
        $this->assertStringContainsString('Parcelado (3x)', $csv);
        $this->assertStringContainsString('Compra aprovada', $csv);
    }

    public function test_menu_mostra_relatorios_so_para_quem_pode(): void
    {
        $this->actingAs(User::factory()->responsavelBaixa()->create())->get('/requisicoes')->assertSee('Relatórios');
        $this->actingAs(User::factory()->create())->get('/requisicoes')->assertDontSee('Relatórios');
    }
}
