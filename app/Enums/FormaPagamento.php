<?php

namespace App\Enums;

enum FormaPagamento: string
{
    case A_VISTA = 'A_VISTA';
    case PARCELADO = 'PARCELADO';

    public function rotulo(): string
    {
        return match ($this) {
            self::A_VISTA => 'À vista',
            self::PARCELADO => 'Parcelado',
        };
    }
}
