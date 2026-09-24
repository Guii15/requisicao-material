<?php

namespace Tests\Feature\Http;

use App\Models\Requisicao;
use App\Models\User;
use App\Services\AssinaturaService;
use App\Services\FichaDaRequisicao;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class RequisicaoPdfTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
    }

    public function test_solicitante_abre_o_pdf_da_propria_requisicao(): void
    {
        $eu = User::factory()->create();
        $requisicao = $this->abrirRequisicao($eu);

        $resposta = $this->actingAs($eu)->get("/requisicoes/{$requisicao->numero}/pdf");

        $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline', $resposta->headers->get('Content-Disposition'));
        $this->assertStringContainsString('REQ-2026-000001.pdf', $resposta->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $resposta->getContent());
    }

    public function test_quem_nao_pode_ver_a_requisicao_nao_abre_o_pdf(): void
    {
        $requisicao = $this->abrirRequisicao(User::factory()->create());
        $url = "/requisicoes/{$requisicao->numero}/pdf";

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->estoque()->create())->get($url)->assertForbidden();
    }

    public function test_documento_mostra_situacao_itens_assinaturas_e_quem_emitiu(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $lider->forceFill(['nome' => 'Leandro Moreira', 'cargo' => 'Analista de Sistemas'])->save();
        $solicitante = User::factory()->for($ti)->create(['nome' => 'Lucas Ramos']);
        $requisicao = $this->abrirRequisicao($solicitante);
        app(RequisicaoWorkflow::class)->aprovar($requisicao, $lider, 'password');

        $html = $this->documento($requisicao->fresh(), $solicitante);

        $this->assertStringContainsString('REQ-2026-000001', $html);
        $this->assertStringContainsString('APROVADA', $html);
        $this->assertStringContainsString('Placa de vídeo RTX 4060 8GB', $html);
        $this->assertStringContainsString('Leandro Moreira', $html);
        $this->assertStringContainsString('Analista de Sistemas', $html);
        foreach ($requisicao->assinaturas()->pluck('hash_documento') as $hash) {
            $this->assertStringContainsString(AssinaturaService::codigoCurto($hash), $html);
        }
        $this->assertStringContainsString('Emitido em 24/09/2026 às 10:00 por Lucas Ramos', $html);
        $this->assertStringContainsString('a situação válida é a que está no sistema', $html);
        $this->assertStringContainsString('Assinaturas íntegras', $html);
    }

    public function test_documento_aguardando_aprovacao_mostra_quem_pode_aprovar(): void
    {
        [$ti, $lider, $sublider] = $this->setorComAprovadores();
        $lider->forceFill(['nome' => 'Leandro Moreira'])->save();
        $sublider->forceFill(['nome' => 'Juliana Duarte'])->save();
        $solicitante = User::factory()->for($ti)->create();
        $requisicao = $this->abrirRequisicao($solicitante);

        $html = $this->documento($requisicao->fresh(), $solicitante);

        $this->assertStringContainsString('AGUARDANDO APROVAÇÃO', $html);
        $this->assertStringContainsString('Juliana Duarte', $html);
        $this->assertStringContainsString('Leandro Moreira', $html);
    }

    public function test_documento_divergente_avisa_que_nao_vale(): void
    {
        $eu = User::factory()->create();
        $requisicao = $this->abrirRequisicao($eu);
        DB::table('requisicao_itens')->where('requisicao_id', $requisicao->id)->update(['qtd_solicitada' => 99]);

        $html = $this->documento($requisicao->fresh(), $eu);

        $this->assertStringContainsString('DOCUMENTO DIVERGENTE', $html);
    }

    public function test_requisicao_cancelada_sai_marcada_com_o_motivo(): void
    {
        $eu = User::factory()->create();
        $requisicao = $this->abrirRequisicao($eu);
        app(RequisicaoWorkflow::class)->cancelar($requisicao, $eu, 'Pedi o item errado.');

        $html = $this->documento($requisicao->fresh(), $eu);

        $this->assertStringContainsString('CANCELADA', $html);
        $this->assertStringContainsString('Pedi o item errado.', $html);
    }

    public function test_detalhe_tem_o_link_do_pdf(): void
    {
        $eu = User::factory()->create();
        $requisicao = $this->abrirRequisicao($eu);

        $this->actingAs($eu)->get("/requisicoes/{$requisicao->numero}")
            ->assertSee('href="'.route('requisicoes.pdf', $requisicao).'"', false);
    }

    private function documento(Requisicao $requisicao, User $emissor): string
    {
        return view('requisicoes.pdf', app(FichaDaRequisicao::class)->paraImpressao($requisicao, $emissor))->render();
    }
}
