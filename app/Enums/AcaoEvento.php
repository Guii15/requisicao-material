<?php

namespace App\Enums;

enum AcaoEvento: string
{
    case CRIACAO = 'CRIACAO';
    case APROVACAO = 'APROVACAO';
    case REPROVACAO = 'REPROVACAO';
    case CANCELAMENTO = 'CANCELAMENTO';
    case SEPARACAO = 'SEPARACAO';
    case LIBERACAO = 'LIBERACAO';
    case REPROVACAO_ESTOQUE = 'REPROVACAO_ESTOQUE';
    case ENTREGA = 'ENTREGA';
    case RECEBIMENTO = 'RECEBIMENTO';
    case DEVOLUCAO = 'DEVOLUCAO';
    case BAIXA = 'BAIXA';
    case AVANCO_AUTOMATICO = 'AVANCO_AUTOMATICO';

    /**
     * Texto da linha do tempo da requisição.
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::CRIACAO => 'Requisição aberta',
            self::APROVACAO => 'Aprovada',
            self::REPROVACAO => 'Reprovada',
            self::CANCELAMENTO => 'Cancelada',
            self::SEPARACAO => 'Separada',
            self::LIBERACAO => 'Liberada pelo estoque',
            self::REPROVACAO_ESTOQUE => 'Reprovada pelo estoque',
            self::ENTREGA => 'Entregue e retirada',
            self::RECEBIMENTO => 'Recebimento confirmado',
            self::DEVOLUCAO => 'Devolução conferida',
            self::BAIXA => 'Baixa registrada',
            self::AVANCO_AUTOMATICO => 'Avançou automaticamente',
        };
    }
}
