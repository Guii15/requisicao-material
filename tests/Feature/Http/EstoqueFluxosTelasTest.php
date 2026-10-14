<?php

namespace Tests\Feature\Http;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * Wiring das telas de Separação+entrega (Teste), Liberação, Devolução e Baixa (rota -> controller
 * -> view -> redirect). As regras de negócio de cada uma já têm teste próprio e mais completo
 * em RequisicaoWorkflowTest e RequisicaoPolicyTest; aqui é só conferir que a tela funciona.
 */
class EstoqueFluxosTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private RequisicaoWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->workflow = app(RequisicaoWorkflow::class);
    }

    private function requisicaoAguardandoLiberacao(string $setor = 'TI'): Requisicao
    {
        [$ti, $lider] = $this->setorComAprovadores($setor);
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        $this->workflow->aprovar($requisicao, $lider, 'password');
        $this->workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), $requisicao->fresh()->itens->pluck('qtd_solicitada', 'id')->all(), 'password');

        return $requisicao->fresh();
    }

    private function requisicaoProntaParaRetirada(string $setor = 'TI'): Requisicao
    {
        [$ti, $lider] = $this->setorComAprovadores($setor);
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $this->workflow->aprovar($requisicao, $lider, 'password');
        $this->workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), $requisicao->fresh()->itens->pluck('qtd_solicitada', 'id')->all(), 'password');

        return $requisicao->fresh();
    }

    private function requisicaoEmPosse(): Requisicao
    {
        $requisicao = $this->requisicaoProntaParaRetirada();
        $this->workflow->entregar($requisicao, User::factory()->estoque()->create(), 'Fulano');

        return $requisicao->fresh();
    }

    private function requisicaoAguardandoBaixa(): Requisicao
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();
        $lider = User::factory()->liderEstoque()->create();
        $this->workflow->liberar($requisicao, $lider, 'password');

        return $requisicao->fresh();
    }

    // ---- Liberação --------------------------------------------------------------------------

    public function test_lider_do_estoque_ve_a_fila_de_liberacao(): void
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();

        $this->actingAs(User::factory()->liderEstoque()->create())->get('/liberacao')
            ->assertOk()
            ->assertSee($requisicao->numero)
            ->assertSee('data-contador-liberacao="1"', false);
    }

    public function test_liberar_pela_tela(): void
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();

        $this->actingAs(User::factory()->liderEstoque()->create())
            ->post("/requisicoes/{$requisicao->numero}/liberar", ['senha' => 'password'])
            ->assertRedirect('/liberacao')
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::AGUARDANDO_BAIXA, $requisicao->fresh()->status);
    }

    // ---- Separar e entregar na mesma tela (Teste) -----------------------------------------------

    public function test_separar_e_entregar_pela_tela_no_teste(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $this->workflow->aprovar($requisicao, $lider, 'password');
        $requisicao = $requisicao->fresh();

        $this->actingAs(User::factory()->estoque()->create())
            ->post("/requisicoes/{$requisicao->numero}/separar-e-entregar", [
                'itens' => $requisicao->itens->pluck('qtd_solicitada', 'id')->map(fn ($qtd) => (string) $qtd)->all(),
                'retirado_por_nome' => 'Maria da Silva',
            ])
            ->assertRedirect('/separacao')
            ->assertSessionHas('sucesso');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::EM_POSSE, $requisicao->status);
        $this->assertSame('Maria da Silva', $requisicao->retirado_por_nome);
    }

    public function test_detalhe_do_teste_aprovado_oferece_separar_e_entregar(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $this->workflow->aprovar($requisicao, $lider, 'password');

        $this->actingAs(User::factory()->estoque()->create())
            ->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Separar e entregar');
    }

    // ---- Devolução -----------------------------------------------------------------------------

    public function test_estoque_ve_a_fila_de_devolucao(): void
    {
        $requisicao = $this->requisicaoEmPosse();

        $this->actingAs(User::factory()->estoque()->create())->get('/devolucao')
            ->assertOk()
            ->assertSee($requisicao->numero);
    }

    public function test_devolver_pela_tela(): void
    {
        $requisicao = $this->requisicaoEmPosse();
        $itens = $requisicao->itens->mapWithKeys(fn ($item) => [
            $item->id => ['ok' => (string) $item->qtd_solicitada, 'defeito' => '0', 'nao_devolvida' => '0'],
        ])->all();

        $this->actingAs(User::factory()->estoque()->create())
            ->post("/requisicoes/{$requisicao->numero}/devolver", [
                'itens' => $itens,
                'devolvido_por_nome' => 'Maria da Silva',
            ])
            ->assertRedirect('/devolucao')
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::DEVOLVIDA, $requisicao->fresh()->status);
        $this->assertSame('Maria da Silva', $requisicao->fresh()->devolvido_por_nome);
    }

    // ---- Baixa ---------------------------------------------------------------------------------

    public function test_responsavel_pela_baixa_ve_a_fila(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();

        $this->actingAs(User::factory()->responsavelBaixa()->create())->get('/baixa')
            ->assertOk()
            ->assertSee($requisicao->numero);
    }

    public function test_dar_baixa_pela_tela(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();

        $this->actingAs(User::factory()->responsavelBaixa()->create())
            ->post("/requisicoes/{$requisicao->numero}/dar-baixa", ['senha' => 'password'])
            ->assertRedirect('/baixa')
            ->assertSessionHas('sucesso');

        $this->assertSame(StatusRequisicao::BAIXADA, $requisicao->fresh()->status);
    }

    public function test_tela_da_baixa_nao_pede_mais_o_print(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();

        $this->actingAs(User::factory()->responsavelBaixa()->create())->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee('Assinar e dar baixa')
            ->assertDontSee('name="comprovante"', false)
            ->assertDontSee('Print do documento de baixa');
    }

    private function requisicaoBaixada(bool $comPrintAntigo = false): Requisicao
    {
        Storage::fake('local');
        $requisicao = $this->requisicaoAguardandoBaixa();
        $this->workflow->darBaixa($requisicao, User::factory()->responsavelBaixa()->create(), 'Conferido no WinThor, NF ok.', 'password');

        if ($comPrintAntigo) {
            // Baixas antigas guardaram o print aqui; o detalhe continua mostrando.
            $caminho = UploadedFile::fake()->image('print.png')->store('baixas', 'local');
            Requisicao::query()->whereKey($requisicao->id)->update(['baixa_comprovante' => $caminho]);
        }

        return $requisicao->fresh();
    }

    public function test_detalhe_mostra_a_observacao_da_baixa_e_o_print_antigo(): void
    {
        $requisicao = $this->requisicaoBaixada(comPrintAntigo: true);

        $this->actingAs($requisicao->solicitante)->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee('Conferido no WinThor, NF ok.')
            ->assertSee(route('requisicoes.comprovante-baixa', $requisicao), false);
    }

    public function test_print_antigo_da_baixa_so_abre_para_quem_pode_ver_a_requisicao(): void
    {
        $requisicao = $this->requisicaoBaixada(comPrintAntigo: true);

        $this->actingAs($requisicao->solicitante)->get(route('requisicoes.comprovante-baixa', $requisicao))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('requisicoes.comprovante-baixa', $requisicao))->assertForbidden();
    }

    public function test_print_da_baixa_de_requisicao_sem_print_da_404(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();

        $this->actingAs($requisicao->solicitante)->get(route('requisicoes.comprovante-baixa', $requisicao))->assertNotFound();
    }

    public function test_pdf_traz_a_observacao_da_baixa(): void
    {
        $requisicao = $this->requisicaoBaixada();

        $this->actingAs($requisicao->solicitante)->get(route('requisicoes.pdf', $requisicao))
            ->assertOk();
    }

    public function test_modal_de_devolucao_empilha_no_celular(): void
    {
        $requisicao = $this->requisicaoEmPosse();

        $this->actingAs(User::factory()->estoque()->create())->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee('grid grid-cols-1', false)
            ->assertSee('flex flex-col', false)
            ->assertSee('w-full shrink-0 sm:w-40', false);
    }

    public function test_detalhe_mostra_a_observacao_da_devolucao_de_cada_item(): void
    {
        $requisicao = $this->requisicaoEmPosse();
        $itens = $requisicao->itens->mapWithKeys(fn ($item) => [$item->id => [
            'ok' => '0', 'defeito' => (string) $item->qtd_solicitada, 'nao_devolvida' => '0',
            'observacao' => "Placa queimada no item {$item->id}",
        ]])->all();
        $this->workflow->devolver($requisicao, User::factory()->estoque()->create(), $itens, 'Maria da Silva');

        $primeiro = $requisicao->itens->first();

        $this->actingAs($requisicao->solicitante)->get(route('requisicoes.show', $requisicao))
            ->assertOk()
            ->assertSee("Placa queimada no item {$primeiro->id}");
    }

    // ---- Botões condicionais no detalhe --------------------------------------------------------

    public function test_detalhe_mostra_botoes_certos_por_etapa(): void
    {
        $liberacao = $this->requisicaoAguardandoLiberacao('TI-A');
        $teste = Requisicao::factory()->status(StatusRequisicao::APROVADA)->create();
        $teste->itens()->create(['descricao' => 'Mouse USB', 'unidade' => 'UN', 'qtd_solicitada' => 1]);

        $this->actingAs(User::factory()->liderEstoque()->create())
            ->get("/requisicoes/{$liberacao->numero}")
            ->assertSee('Liberar');

        $this->actingAs(User::factory()->estoque()->create())
            ->get("/requisicoes/{$teste->numero}")
            ->assertSee('Separar e entregar')
            ->assertDontSee('name="entregar"', false);
    }
}
