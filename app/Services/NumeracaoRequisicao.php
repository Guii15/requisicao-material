<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Gera o número da requisição (REQ-2026-000123), sequencial por ano.
 *
 * Deve ser chamado dentro da transação que grava a requisição: o contador é travado
 * com lockForUpdate (sem número duplicado) e, se a transação for desfeita, volta junto
 * (sem buraco na sequência).
 */
class NumeracaoRequisicao
{
    public function proximo(): string
    {
        $ano = now()->year;
        $chave = "REQ-{$ano}";

        return DB::transaction(function () use ($ano, $chave) {
            DB::table('numeracoes')->insertOrIgnore(['chave' => $chave, 'ultimo_numero' => 0]);

            $numero = 1 + (int) DB::table('numeracoes')
                ->where('chave', $chave)
                ->lockForUpdate()
                ->value('ultimo_numero');

            DB::table('numeracoes')->where('chave', $chave)->update(['ultimo_numero' => $numero]);

            return sprintf('REQ-%d-%06d', $ano, $numero);
        });
    }
}
