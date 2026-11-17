<?php

namespace App\Support;

use App\Enums\StatusRequisicao as S;
use App\Enums\TipoRequisicao;

/**
 * Traduz o status da requisição nas etapas da trilha de progresso mostrada nas listas.
 */
final class Trilha
{
    /**
     * @return list<array{nome: string, estado: string}> estado: feita, atual, pendente, falha ou cancelada
     */
    public static function etapas(S $status, TipoRequisicao $tipo): array
    {
        $teste = $tipo === TipoRequisicao::TESTE;

        if ($tipo === TipoRequisicao::TRANSFERENCIA_FILIAL) {
            return self::transferencia($status);
        }

        // Pedido de compra: vai direto para a responsável pela baixa decidir.
        if ($tipo === TipoRequisicao::COMPRA_FUNCIONARIO) {
            return [
                ['nome' => 'Pedido', 'estado' => $status === S::CANCELADA ? 'cancelada' : 'feita'],
                ['nome' => 'Decisão', 'estado' => match ($status) {
                    S::COMPRA_APROVADA => 'feita',
                    S::COMPRA_REPROVADA => 'falha',
                    S::CANCELADA => 'cancelada',
                    default => 'atual',
                }],
            ];
        }

        $nomes = $teste
            ? ['Liberação', 'Separação', 'Retirada', 'Em posse', 'Devolução']
            : ['Liberação', 'Separação', 'Baixa'];

        if ($status === S::CANCELADA) {
            return array_map(fn (string $nome) => ['nome' => $nome, 'estado' => 'cancelada'], $nomes);
        }

        if ($status === S::BAIXADA || $status === S::DEVOLVIDA) {
            return array_map(fn (string $nome) => ['nome' => $nome, 'estado' => 'feita'], $nomes);
        }

        // Posição da etapa em que a requisição está.
        $posicao = $teste
            ? match ($status) {
                S::AGUARDANDO_APROVACAO, S::REPROVADA => 0,
                S::APROVADA, S::EM_SEPARACAO => 1,
                S::PRONTA_PARA_RETIRADA => 2,
                S::ENTREGUE, S::EM_POSSE => 3,
                default => 4,
            }
        : match ($status) {
            // Pedido novo vai direto para os líderes do estoque liberarem (17/11/2026).
            S::AGUARDANDO_APROVACAO, S::REPROVADA, S::AGUARDANDO_LIBERACAO_ESTOQUE, S::REPROVADA_ESTOQUE => 0,
            S::APROVADA, S::LIBERADA, S::EM_SEPARACAO => 1,
            default => 2,
        };

        $falhou = in_array($status, [S::REPROVADA, S::REPROVADA_ESTOQUE, S::DEVOLUCAO_COM_PENDENCIA], true);

        return array_map(fn (string $nome, int $i) => [
            'nome' => $nome,
            'estado' => match (true) {
                $i < $posicao => 'feita',
                $i === $posicao => $falhou ? 'falha' : 'atual',
                default => 'pendente',
            },
        ], $nomes, array_keys($nomes));
    }

    /**
     * @return list<array{nome: string, estado: string}>
     */
    private static function transferencia(S $status): array
    {
        $nomes = ['Aprovação', 'Transferência', 'Envio', 'Recebimento'];

        if ($status === S::CANCELADA) {
            return array_map(fn (string $nome) => ['nome' => $nome, 'estado' => 'cancelada'], $nomes);
        }

        $posicao = match ($status) {
            S::AGUARDANDO_APROVACAO, S::REPROVADA => 0,
            S::APROVADA, S::AGUARDANDO_TRANSFERENCIA => 1,
            S::AGUARDANDO_ENVIO => 2,
            S::AGUARDANDO_RECEBIMENTO, S::REPROVADA_ESTOQUE => 3,
            default => 4,
        };
        $falhou = in_array($status, [S::REPROVADA, S::REPROVADA_ESTOQUE], true);

        return array_map(fn (string $nome, int $i) => [
            'nome' => $nome,
            'estado' => match (true) {
                $i < $posicao => 'feita',
                $i === $posicao => $falhou ? 'falha' : 'atual',
                default => 'pendente',
            },
        ], $nomes, array_keys($nomes));
    }
}
