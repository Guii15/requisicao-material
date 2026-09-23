<?php

namespace Tests\Feature\Models;

use App\Enums\EtapaAssinatura;
use App\Enums\MetodoAssinatura;
use App\Exceptions\RegistroProtegidoException;
use App\Models\Requisicao;
use App\Models\RequisicaoAssinatura;
use App\Models\RequisicaoEvento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrosImutaveisTest extends TestCase
{
    use RefreshDatabase;

    private function criarEvento(): RequisicaoEvento
    {
        $requisicao = Requisicao::factory()->create();

        return RequisicaoEvento::create([
            'requisicao_id' => $requisicao->id,
            'user_id' => $requisicao->solicitante_id,
            'acao' => 'CRIACAO',
            'status_de' => null,
            'status_para' => 'AGUARDANDO_APROVACAO',
            'dados' => ['itens' => 1],
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
    }

    private function criarAssinatura(): RequisicaoAssinatura
    {
        $requisicao = Requisicao::factory()->create();

        return RequisicaoAssinatura::create([
            'requisicao_id' => $requisicao->id,
            'etapa' => EtapaAssinatura::SOLICITACAO,
            'user_id' => $requisicao->solicitante_id,
            'nome_assinante' => 'Fulano',
            'cargo_assinante' => 'Auxiliar',
            'metodo' => MetodoAssinatura::SENHA,
            'conteudo_assinado' => '{"numero":"REQ-2026-000001"}',
            'hash_documento' => str_repeat('a', 64),
            'hash_anterior' => null,
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'assinado_em' => now(),
        ]);
    }

    public function test_evento_nao_pode_ser_editado(): void
    {
        $evento = $this->criarEvento();

        $this->expectException(RegistroProtegidoException::class);

        $evento->forceFill(['ip' => '10.0.0.99'])->save();
    }

    public function test_evento_nao_pode_ser_apagado(): void
    {
        $evento = $this->criarEvento();

        $this->expectException(RegistroProtegidoException::class);

        $evento->delete();
    }

    public function test_evento_nao_pode_ser_editado_nem_pelo_workflow(): void
    {
        $evento = $this->criarEvento();

        $this->expectException(RegistroProtegidoException::class);

        Requisicao::viaWorkflow(fn () => $evento->forceFill(['ip' => '10.0.0.99'])->save());
    }

    public function test_assinatura_nao_pode_ser_editada(): void
    {
        $assinatura = $this->criarAssinatura();

        $this->expectException(RegistroProtegidoException::class);

        $assinatura->forceFill(['nome_assinante' => 'Outra pessoa'])->save();
    }

    public function test_assinatura_nao_pode_ser_apagada(): void
    {
        $assinatura = $this->criarAssinatura();

        $this->expectException(RegistroProtegidoException::class);

        $assinatura->delete();
    }

    public function test_evento_guarda_dados_em_json_e_data_de_criacao(): void
    {
        $evento = $this->criarEvento()->fresh();

        $this->assertSame(['itens' => 1], $evento->dados);
        $this->assertNotNull($evento->created_at);
    }

    public function test_assinatura_guarda_o_conteudo_assinado_exatamente_como_foi_gravado(): void
    {
        $assinatura = $this->criarAssinatura()->fresh();

        $this->assertSame('{"numero":"REQ-2026-000001"}', $assinatura->conteudo_assinado);
        $this->assertSame(EtapaAssinatura::SOLICITACAO, $assinatura->etapa);
        $this->assertSame(MetodoAssinatura::SENHA, $assinatura->metodo);
    }
}
