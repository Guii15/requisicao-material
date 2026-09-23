<?php

namespace App\Enums;

enum AcaoEvento: string
{
    case CRIACAO = 'CRIACAO';
    case APROVACAO = 'APROVACAO';
    case REPROVACAO = 'REPROVACAO';
    case CANCELAMENTO = 'CANCELAMENTO';
    case AVANCO_AUTOMATICO = 'AVANCO_AUTOMATICO';

    /**
     * Texto da linha do tempo da requisição.
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::CRIACAO => 'Requisição aberta',
            self::APROVACAO => 'Aprovada pelo setor',
            self::REPROVACAO => 'Reprovada pelo setor',
            self::CANCELAMENTO => 'Cancelada',
            self::AVANCO_AUTOMATICO => 'Avançou automaticamente',
        };
    }
}
