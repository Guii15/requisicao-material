<?php

namespace Tests\Feature\Services;

use App\Services\NumeracaoRequisicao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NumeracaoRequisicaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeros_sao_sequenciais_no_formato_da_spec(): void
    {
        $this->travelTo('2026-09-23 10:00:00');
        $numeracao = app(NumeracaoRequisicao::class);

        $this->assertSame('REQ-2026-000001', $numeracao->proximo());
        $this->assertSame('REQ-2026-000002', $numeracao->proximo());
    }

    public function test_sequencia_recomeca_no_ano_novo(): void
    {
        $numeracao = app(NumeracaoRequisicao::class);

        $this->travelTo('2026-12-31 23:59:00');
        $numeracao->proximo();
        $numeracao->proximo();

        $this->travelTo('2027-01-01 00:01:00');
        $this->assertSame('REQ-2027-000001', $numeracao->proximo());
    }

    public function test_numero_de_transacao_desfeita_nao_deixa_buraco(): void
    {
        $this->travelTo('2026-09-23 10:00:00');
        $numeracao = app(NumeracaoRequisicao::class);
        $numeracao->proximo();

        try {
            DB::transaction(function () use ($numeracao) {
                $numeracao->proximo();
                throw new \RuntimeException('falhou ao salvar a requisição');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('REQ-2026-000002', $numeracao->proximo());
    }
}
