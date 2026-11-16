<?php

namespace App\Enums;

/**
 * Filiais (empresas) entre as quais o produto é transferido no WinThor.
 */
enum Filial: string
{
    case SHOWROOM = 'SHOWROOM';
    case BINARIO = 'BINARIO';
    case BINARIO_SHOWROOM = 'BINARIO_SHOWROOM';

    public function rotulo(): string
    {
        return match ($this) {
            self::SHOWROOM => 'Showroom',
            self::BINARIO => 'Binário',
            self::BINARIO_SHOWROOM => 'Binário Showroom',
        };
    }
}
