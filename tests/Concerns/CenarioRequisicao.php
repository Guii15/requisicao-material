<?php

namespace Tests\Concerns;

use App\Enums\PapelSetor;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\RequisicaoWorkflow;

/**
 * Monta os cenários usados nos testes de fluxo. A senha de todos os usuários da factory é "password".
 */
trait CenarioRequisicao
{
    /**
     * @return array{0: Setor, 1: User, 2: User}
     */
    protected function setorComAprovadores(string $nome = 'TI'): array
    {
        $setor = Setor::factory()->create(['nome' => $nome]);
        $lider = User::factory()->for($setor)->aprovadorDe($setor, PapelSetor::LIDER)->create();
        $sublider = User::factory()->for($setor)->aprovadorDe($setor, PapelSetor::SUBLIDER)->create();

        return [$setor, $lider, $sublider];
    }

    /**
     * Dados válidos de uma requisição de TESTE aberta na quinta 24/09/2026 (prazo até 29/09).
     *
     * @param  array<string, mixed>  $sobrescrever
     * @return array<string, mixed>
     */
    protected function dadosRequisicao(array $sobrescrever = []): array
    {
        return array_merge([
            'tipo' => 'TESTE',
            'itens' => [
                ['descricao' => 'Placa de vídeo RTX 4060 8GB', 'unidade' => 'UN', 'quantidade' => '1'],
                ['descricao' => 'Cabo HDMI 2.1 2m', 'unidade' => 'UN', 'quantidade' => '2'],
            ],
            'justificativa' => 'Teste de compatibilidade antes da montagem do cliente.',
            'finalidade' => 'Validar a placa no PC do pedido 88412.',
            'data_prevista_devolucao' => '2026-09-28',
        ], $sobrescrever);
    }

    /**
     * @param  array<string, mixed>  $sobrescrever
     */
    protected function abrirRequisicao(User $solicitante, array $sobrescrever = []): Requisicao
    {
        return app(RequisicaoWorkflow::class)->criar($solicitante, $this->dadosRequisicao($sobrescrever), 'password');
    }
}
