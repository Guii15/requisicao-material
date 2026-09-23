<?php

namespace App\Enums;

enum TipoRequisicao: string
{
    case TESTE = 'TESTE';
    case USO_CONSUMO = 'USO_CONSUMO';

    public function rotulo(): string
    {
        return match ($this) {
            self::TESTE => 'TESTE',
            self::USO_CONSUMO => 'USO E CONSUMO',
        };
    }

    public function exigeDevolucao(): bool
    {
        return $this === self::TESTE;
    }

    public function exigeLiberacaoEstoque(): bool
    {
        return $this === self::USO_CONSUMO;
    }

    public function exigeBaixa(): bool
    {
        return $this === self::USO_CONSUMO;
    }
}
