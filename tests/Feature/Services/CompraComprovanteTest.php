<?php

namespace Tests\Feature\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * O print do WinThor fica no pedido de compra (a Erica anexa ao aprovar), não na baixa.
 */
class CompraComprovanteTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private RequisicaoWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-09-24 10:00:00');
        $this->workflow = app(RequisicaoWorkflow::class);
    }

    public function test_aprovar_a_compra_guarda_o_print_do_winthor(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create();

        $this->workflow->decidirCompra($requisicao, $erica, true, null, 'A_VISTA', null, [], UploadedFile::fake()->image('winthor.png'));

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::COMPRA_APROVADA, $requisicao->status);
        $this->assertNotNull($requisicao->compra_comprovante);
        Storage::disk('local')->assertExists($requisicao->compra_comprovante);
    }

    public function test_aprovar_a_compra_sem_print_e_recusado(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create();

        try {
            $this->workflow->decidirCompra($requisicao, $erica, true, null, 'A_VISTA', null, [], null);
            $this->fail('Deveria exigir o print do WinThor.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('comprovante', $e->errors());
        }

        $this->assertSame(StatusRequisicao::AGUARDANDO_COMPRA, $requisicao->fresh()->status);
    }

    public function test_aprovar_a_compra_com_arquivo_que_nao_e_imagem_e_recusado(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create();

        $this->expectException(ValidationException::class);

        $this->workflow->decidirCompra($requisicao, $erica, true, null, 'A_VISTA', null, [], UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'));
    }

    public function test_aprovar_a_compra_com_print_maior_que_5mb_e_recusado(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create();

        $this->expectException(ValidationException::class);

        $this->workflow->decidirCompra($requisicao, $erica, true, null, 'A_VISTA', null, [], UploadedFile::fake()->create('grande.png', 6000, 'image/png'));
    }

    public function test_reprovar_a_compra_nao_pede_print(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $requisicao = Requisicao::factory()->compra()->create();

        $this->workflow->decidirCompra($requisicao, $erica, false, 'Sem orçamento este mês');

        $this->assertSame(StatusRequisicao::COMPRA_REPROVADA, $requisicao->fresh()->status);
        $this->assertNull($requisicao->fresh()->compra_comprovante);
    }

    public function test_baixa_nao_pede_mais_print(): void
    {
        [$setor, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($setor)->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        $this->workflow->aprovar($requisicao, $lider, null);
        $quantidades = $requisicao->itens->mapWithKeys(fn ($item) => [$item->id => (string) $item->qtd_solicitada])->all();
        $this->workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), $quantidades, null);
        $this->workflow->liberar($requisicao->fresh(), User::factory()->liderEstoque()->create(), null);

        $this->workflow->darBaixa($requisicao->fresh(), User::factory()->responsavelBaixa()->create(), 'Conferido no WinThor', null);

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::BAIXADA, $requisicao->status);
        $this->assertSame('Conferido no WinThor', $requisicao->baixa_observacao);
        $this->assertNull($requisicao->baixa_comprovante);
    }
}
