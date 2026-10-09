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
        $nomes = $teste
            ? ['Aprovação', 'Separação', 'Retirada', 'Em posse', 'Devolução']
            : ['Aprovação', 'Separação', 'Liberação', 'Retirada', 'Baixa'];

        if ($status === S::CANCELADA) {
            return array_map(fn (string $nome) => ['nome' => $nome, 'estado' => 'cancelada'], $nomes);
        }

        if ($status === S::BAIXADA || $status === S::DEVOLVIDA) {
            return array_map(fn (string $nome) => ['nome' => $nome, 'estado' => 'feita'], $nomes);
        }

        // Posição (0 a 4) da etapa em que a requisição está.
        $posicao = $teste
            ? match ($status) {
                S::AGUARDANDO_APROVACAO, S::REPROVADA => 0,
                S::APROVADA, S::EM_SEPARACAO => 1,
                S::PRONTA_PARA_RETIRADA => 2,
                S::ENTREGUE, S::EM_POSSE => 3,
                default => 4,
            }
            : match ($status) {
                S::AGUARDANDO_APROVACAO, S::REPROVADA => 0,
                S::APROVADA, S::EM_SEPARACAO => 1,
                S::AGUARDANDO_LIBERACAO_ESTOQUE, S::LIBERADA, S::REPROVADA_ESTOQUE => 2,
                S::PRONTA_PARA_RETIRADA => 3,
                default => 4,
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
}
