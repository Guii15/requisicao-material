<?php

namespace Tests\Feature\Services;

use App\Models\Requisicao;
use App\Models\User;
use App\Services\AssinaturaService;
use App\Services\RequisicaoWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class AssinaturaServiceTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private AssinaturaService $assinaturas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->assinaturas = app(AssinaturaService::class);
    }

    private function requisicaoAprovada(): Requisicao
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        app(RequisicaoWorkflow::class)->aprovar($requisicao, $lider, 'password');

        return $requisicao->fresh();
    }

    public function test_assinaturas_sao_encadeadas(): void
    {
        [$solicitacao, $aprovacao] = $this->requisicaoAprovada()->assinaturas->all();

        $this->assertNull($solicitacao->hash_anterior);
        $this->assertSame($solicitacao->hash_documento, $aprovacao->hash_anterior);
        $this->assertSame(hash('sha256', $aprovacao->conteudo_assinado), $aprovacao->hash_documento);
    }

    public function test_conteudo_assinado_tem_os_dados_da_spec(): void
    {
        $assinatura = $this->requisicaoAprovada()->assinaturas->last();
        $conteudo = json_decode($assinatura->conteudo_assinado, true);

        $this->assertSame('APROVACAO_SETOR', $conteudo['etapa']);
        $this->assertSame($assinatura->hash_anterior, $conteudo['hash_anterior']);
        $this->assertSame('TESTE', $conteudo['documento']['tipo']);
        $this->assertSame('Placa de vídeo RTX 4060 8GB', $conteudo['documento']['itens'][0]['descricao']);
        $this->assertSame('1.000', $conteudo['documento']['itens'][0]['qtd_solicitada']);
        $this->assertSame('APROVADA', $conteudo['documento']['status']);
    }

    public function test_requisicao_sem_alteracao_esta_integra(): void
    {
        $verificacao = $this->assinaturas->verificar($this->requisicaoAprovada());

        $this->assertTrue($verificacao->integro, implode(' | ', $verificacao->problemas));
    }

    public function test_alterar_item_direto_no_banco_acusa_divergencia(): void
    {
        $requisicao = $this->requisicaoAprovada();

        DB::table('requisicao_itens')->where('requisicao_id', $requisicao->id)->update(['qtd_solicitada' => 10]);

        $this->assertFalse($this->assinaturas->verificar($requisicao->fresh())->integro);
    }

    public function test_alterar_finalidade_direto_no_banco_acusa_divergencia(): void
    {
        $requisicao = $this->requisicaoAprovada();

        DB::table('requisicoes')->where('id', $requisicao->id)->update(['finalidade' => 'Uso pessoal']);

        $this->assertFalse($this->assinaturas->verificar($requisicao->fresh())->integro);
    }

    public function test_alterar_conteudo_da_assinatura_no_banco_acusa_divergencia(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $assinatura = $requisicao->assinaturas->first();

        DB::table('requisicao_assinaturas')->where('id', $assinatura->id)
            ->update(['conteudo_assinado' => str_replace('RTX 4060', 'RTX 4090', $assinatura->conteudo_assinado)]);

        $this->assertFalse($this->assinaturas->verificar($requisicao->fresh())->integro);
    }

    public function test_pular_a_aprovacao_mudando_o_status_no_banco_acusa_divergencia(): void
    {
        [$ti] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        DB::table('requisicoes')->where('id', $requisicao->id)->update(['status' => 'APROVADA']);

        $verificacao = $this->assinaturas->verificar($requisicao->fresh());
        $this->assertFalse($verificacao->integro);
        $this->assertStringContainsString('Aprovado por', implode(' ', $verificacao->problemas));
    }

    public function test_codigo_curto_para_o_pdf(): void
    {
        $this->assertSame('A3F9-1C7E-88B2', AssinaturaService::codigoCurto('a3f91c7e88b2'.str_repeat('0', 52)));
    }
}
