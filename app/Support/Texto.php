<?php

namespace App\Support;

/**
 * Pequenos ajustes de texto para exibição.
 */
final class Texto
{
    /**
     * Nome de setor para a tela: "ESTOQUE" vira "Estoque"; siglas curtas ("RH", "TI") continuam em maiúsculas.
     */
    public static function setor(string $nome): string
    {
        return mb_strlen($nome) <= 3
            ? mb_strtoupper($nome)
            : mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE);
    }
}
