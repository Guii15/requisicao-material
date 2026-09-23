<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Feriados nacionais (Lei 662/1949, 6.802/1980, 14.759/2023). Os municipais de Varginha
 * e os pontos facultativos o admin cadastra na tabela de feriados.
 */
final class FeriadosNacionais
{
    /**
     * Domingo de Páscoa pelo algoritmo de Meeus/Jones/Butcher (calendário gregoriano).
     */
    public static function pascoa(int $ano): CarbonImmutable
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($ano, $mes, $dia);
    }

    /**
     * @return array<string, string> data (Y-m-d) => descrição, em ordem de data
     */
    public static function doAno(int $ano): array
    {
        $feriados = [
            "{$ano}-01-01" => 'Confraternização Universal',
            self::pascoa($ano)->subDays(2)->toDateString() => 'Paixão de Cristo',
            "{$ano}-04-21" => 'Tiradentes',
            "{$ano}-05-01" => 'Dia do Trabalho',
            "{$ano}-09-07" => 'Independência do Brasil',
            "{$ano}-10-12" => 'Nossa Senhora Aparecida',
            "{$ano}-11-02" => 'Finados',
            "{$ano}-11-15" => 'Proclamação da República',
            "{$ano}-11-20" => 'Dia Nacional de Zumbi e da Consciência Negra',
            "{$ano}-12-25" => 'Natal',
        ];

        ksort($feriados);

        return $feriados;
    }
}
