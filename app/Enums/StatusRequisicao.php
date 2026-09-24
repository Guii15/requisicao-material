<?php

namespace App\Enums;

/**
 * Status da requisição e tabela de transições.
 *
 * Este enum é a única fonte da verdade sobre QUAIS transições existem.
 * QUEM pode executar cada uma fica na RequisicaoPolicy; QUANDO/COMO, no RequisicaoWorkflow.
 */
enum StatusRequisicao: string
{
    case AGUARDANDO_APROVACAO = 'AGUARDANDO_APROVACAO';
    case APROVADA = 'APROVADA';
    case REPROVADA = 'REPROVADA';
    case CANCELADA = 'CANCELADA';
    case EM_SEPARACAO = 'EM_SEPARACAO';
    case AGUARDANDO_LIBERACAO_ESTOQUE = 'AGUARDANDO_LIBERACAO_ESTOQUE';
    case LIBERADA = 'LIBERADA';
    case REPROVADA_ESTOQUE = 'REPROVADA_ESTOQUE';
    case PRONTA_PARA_RETIRADA = 'PRONTA_PARA_RETIRADA';
    case ENTREGUE = 'ENTREGUE';
    case EM_POSSE = 'EM_POSSE';
    case DEVOLVIDA = 'DEVOLVIDA';
    case DEVOLUCAO_COM_PENDENCIA = 'DEVOLUCAO_COM_PENDENCIA';
    case AGUARDANDO_BAIXA = 'AGUARDANDO_BAIXA';
    case BAIXADA = 'BAIXADA';

    public function rotulo(): string
    {
        return match ($this) {
            self::AGUARDANDO_APROVACAO => 'Aguardando aprovação',
            self::APROVADA => 'Aprovada',
            self::REPROVADA => 'Reprovada',
            self::CANCELADA => 'Cancelada',
            self::EM_SEPARACAO => 'Em separação',
            self::AGUARDANDO_LIBERACAO_ESTOQUE => 'Aguardando liberação do estoque',
            self::LIBERADA => 'Liberada',
            self::REPROVADA_ESTOQUE => 'Reprovada pelo estoque',
            self::PRONTA_PARA_RETIRADA => 'Pronta para retirada',
            self::ENTREGUE => 'Entregue',
            self::EM_POSSE => 'Em posse',
            self::DEVOLVIDA => 'Devolvida',
            self::DEVOLUCAO_COM_PENDENCIA => 'Devolução com pendência',
            self::AGUARDANDO_BAIXA => 'Aguardando baixa',
            self::BAIXADA => 'Baixada',
        };
    }

    /**
     * Agrupamento usado no selo de status: ícone e cor só onde há significado.
     */
    public function grupo(): string
    {
        return match ($this) {
            self::AGUARDANDO_APROVACAO, self::AGUARDANDO_LIBERACAO_ESTOQUE, self::AGUARDANDO_BAIXA => 'aguardando',
            self::DEVOLVIDA, self::BAIXADA => 'concluida',
            self::REPROVADA, self::REPROVADA_ESTOQUE, self::CANCELADA => 'encerrada',
            self::DEVOLUCAO_COM_PENDENCIA => 'alerta',
            default => 'andamento',
        };
    }

    /**
     * @return list<self>
     */
    public function destinos(TipoRequisicao $tipo): array
    {
        $teste = $tipo === TipoRequisicao::TESTE;

        return match ($this) {
            self::AGUARDANDO_APROVACAO => [self::APROVADA, self::REPROVADA, self::CANCELADA],
            self::APROVADA => [self::EM_SEPARACAO, self::CANCELADA],
            self::EM_SEPARACAO => [$teste ? self::PRONTA_PARA_RETIRADA : self::AGUARDANDO_LIBERACAO_ESTOQUE, self::CANCELADA],
            self::AGUARDANDO_LIBERACAO_ESTOQUE => $teste ? [] : [self::LIBERADA, self::REPROVADA_ESTOQUE, self::CANCELADA],
            self::LIBERADA => $teste ? [] : [self::PRONTA_PARA_RETIRADA],
            self::PRONTA_PARA_RETIRADA => [self::ENTREGUE, self::CANCELADA],
            self::ENTREGUE => [$teste ? self::EM_POSSE : self::AGUARDANDO_BAIXA],
            self::EM_POSSE => $teste ? [self::DEVOLVIDA, self::DEVOLUCAO_COM_PENDENCIA] : [],
            self::AGUARDANDO_BAIXA => $teste ? [] : [self::BAIXADA],
            default => [],
        };
    }

    public function podeIrPara(self $para, TipoRequisicao $tipo): bool
    {
        return in_array($para, $this->destinos($tipo), true);
    }

    /**
     * Status de passagem: ficam gravados no histórico, mas a requisição segue na hora para o próximo.
     */
    public function proximoAutomatico(TipoRequisicao $tipo): ?self
    {
        return match ($this) {
            self::EM_SEPARACAO, self::LIBERADA, self::ENTREGUE => $this->destinos($tipo)[0] ?? null,
            default => null,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [
            self::REPROVADA,
            self::CANCELADA,
            self::REPROVADA_ESTOQUE,
            self::DEVOLVIDA,
            self::DEVOLUCAO_COM_PENDENCIA,
            self::BAIXADA,
        ], true);
    }

    public function visivelParaEstoque(): bool
    {
        return ! in_array($this, [self::AGUARDANDO_APROVACAO, self::REPROVADA, self::CANCELADA], true);
    }
}
