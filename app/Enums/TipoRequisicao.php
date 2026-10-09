<?php

namespace App\Enums;

enum TipoRequisicao: string
{
    case TESTE = 'TESTE';
    case USO_CONSUMO = 'USO_CONSUMO';
    case COMPRA_FUNCIONARIO = 'COMPRA_FUNCIONARIO';

    public function rotulo(): string
    {
        return match ($this) {
            self::TESTE => 'TESTE',
            self::USO_CONSUMO => 'USO E CONSUMO',
            self::COMPRA_FUNCIONARIO => 'COMPRA DE FUNCIONÁRIO',
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
