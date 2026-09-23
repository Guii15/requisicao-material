<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class Duracao
{
    /**
     * Tempo decorrido em formato curto para tabelas: "agora", "5 min", "3 h", "2 d".
     */
    public static function curta(CarbonInterface $desde, ?CarbonInterface $agora = null): string
    {
        $agora ??= now();
        $segundos = max(0, $agora->getTimestamp() - $desde->getTimestamp());

        return match (true) {
            $segundos < 60 => 'agora',
            $segundos < 3600 => intdiv($segundos, 60).' min',
            $segundos < 86400 => intdiv($segundos, 3600).' h',
            default => intdiv($segundos, 86400).' d',
        };
    }

    /**
     * Mesma coisa em frase: "agora" ou "há 3 h".
     */
    public static function ha(CarbonInterface $desde, ?CarbonInterface $agora = null): string
    {
        $curta = self::curta($desde, $agora);

        return $curta === 'agora' ? $curta : "há {$curta}";
    }
}
