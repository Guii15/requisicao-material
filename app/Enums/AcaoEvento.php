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
    case COMPRA_APROVADA = 'COMPRA_APROVADA';
    case COMPRA_REPROVADA = 'COMPRA_REPROVADA';
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case ENVIO = 'ENVIO';

    /**
     * Texto da linha do tempo da requisição.
     */
    public function rotulo(?TipoRequisicao $tipo = null): string
    {
        // Na transferência, quem recusa o recebimento é quem pediu, não o estoque.
        if ($this === self::REPROVACAO_ESTOQUE && $tipo === TipoRequisicao::TRANSFERENCIA_FILIAL) {
            return 'Recebimento recusado';
        }

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
            self::COMPRA_APROVADA => 'Compra aprovada',
            self::COMPRA_REPROVADA => 'Compra reprovada',
            self::TRANSFERENCIA => 'Transferida no WinThor',
            self::ENVIO => 'Produto enviado',
        };
    }
}
