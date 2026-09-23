<?php

namespace Database\Seeders;

use App\Models\Feriado;
use App\Support\FeriadosNacionais;
use Illuminate\Database\Seeder;

/**
 * Cadastra os feriados nacionais do ano atual e do próximo. Pode rodar em produção
 * (php artisan db:seed --class=FeriadosNacionaisSeeder): não duplica.
 */
class FeriadosNacionaisSeeder extends Seeder
{
    public function run(): void
    {
        $ano = now()->year;

        foreach ([$ano, $ano + 1] as $anoFeriado) {
            foreach (FeriadosNacionais::doAno($anoFeriado) as $data => $descricao) {
                if (! Feriado::query()->whereDate('data', $data)->exists()) {
                    Feriado::create(['data' => $data, 'descricao' => $descricao]);
                }
            }
        }
    }
}
