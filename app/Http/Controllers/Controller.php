<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Campo de texto do formulário, ou nulo se não veio texto (ex.: alguém mandou um array).
     */
    protected function texto(Request $request, string $campo): ?string
    {
        $valor = $request->input($campo);

        return is_string($valor) ? $valor : null;
    }

    /**
     * Itens marcados como "Recusar" na decisão por item: id do item => motivo.
     *
     * @return array<int, string>
     */
    protected function recusasDoFormulario(Request $request): array
    {
        $recusas = [];

        foreach ((array) $request->input('itens', []) as $id => $linha) {
            if (is_numeric($id) && is_array($linha) && ($linha['decisao'] ?? null) === 'recusar') {
                $recusas[(int) $id] = is_string($linha['motivo'] ?? null) ? $linha['motivo'] : '';
            }
        }

        return $recusas;
    }
}
