<?php

namespace App\Enums;

/**
 * Rótulo do aprovador no setor. Na regra de aprovação os dois valem igual:
 * todos os aprovadores do setor veem a fila e o primeiro que agir resolve.
 */
enum PapelSetor: string
{
    case LIDER = 'LIDER';
    case SUBLIDER = 'SUBLIDER';

    public function rotulo(): string
    {
        return match ($this) {
            self::LIDER => 'Líder',
            self::SUBLIDER => 'Sublíder',
        };
    }
}
