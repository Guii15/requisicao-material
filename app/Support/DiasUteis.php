<?php

namespace App\Support;

use App\Models\Feriado;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Conta dias úteis (segunda a sexta, fora os feriados cadastrados).
 */
final class DiasUteis
{
    /** @var array<string, true> */
    private array $feriados = [];

    /**
     * @param  iterable<string|CarbonInterface>  $feriados
     */
    public function __construct(iterable $feriados)
    {
        foreach ($feriados as $feriado) {
            $this->feriados[CarbonImmutable::parse($feriado)->toDateString()] = true;
        }
    }

    public static function doBanco(): self
    {
        return new self(Feriado::query()->pluck('data'));
    }

    public function ehDiaUtil(CarbonInterface $data): bool
    {
        return ! $data->isWeekend() && ! isset($this->feriados[$data->toDateString()]);
    }

    /**
     * Data que fica $dias dias úteis depois de $data (o próprio dia de $data não conta).
     */
    public function somar(CarbonInterface $data, int $dias): CarbonImmutable
    {
        $atual = CarbonImmutable::instance($data)->startOfDay();

        for ($contados = 0; $contados < $dias;) {
            $atual = $atual->addDay();

            if ($this->ehDiaUtil($atual)) {
                $contados++;
            }
        }

        return $atual;
    }
}
