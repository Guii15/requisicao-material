<?php

namespace App\Support;

final class Quantidade
{
    /**
     * Quantidade para exibição: "1", "2,5", "1.234,125". Nulo vira "-".
     */
    public static function formatar(?string $valor): string
    {
        if ($valor === null) {
            return '-';
        }

        [$inteiro, $decimal] = array_pad(explode('.', $valor, 2), 2, '');
        $decimal = rtrim($decimal, '0');
        $inteiro = number_format((int) $inteiro, 0, ',', '.');

        return $decimal === '' ? $inteiro : "{$inteiro},{$decimal}";
    }
}
