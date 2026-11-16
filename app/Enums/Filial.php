<?php

namespace App\Enums;

/**
 * Filiais (empresas) entre as quais o produto é transferido no WinThor.
 * Qualquer uma pode mandar para qualquer outra.
 */
enum Filial: string
{
    case SHOWROOM = 'SHOWROOM';
    case BINARIO = 'BINARIO';
    case NINJA_PLACE = 'NINJA_PLACE';

    public function rotulo(): string
    {
        return match ($this) {
            self::SHOWROOM => 'Showroom',
            self::BINARIO => 'Binário',
            self::NINJA_PLACE => 'Ninja Place',
        };
    }
}
