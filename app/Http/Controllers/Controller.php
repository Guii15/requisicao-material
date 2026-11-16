<?php

namespace App\Http\Controllers;

use App\Models\Requisicao;
use Illuminate\Http\RedirectResponse;
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

    /**
     * Depois de assinar uma etapa, volta para a requisição, para a pessoa ver o status novo na hora.
     * Se ela não puder mais abrir a requisição, volta para a fila de onde veio.
     */
    protected function depoisDeAssinar(Request $request, Requisicao $requisicao, string $fila): RedirectResponse
    {
        return $request->user()->can('view', $requisicao->refresh())
            ? redirect()->route('requisicoes.show', $requisicao)
            : redirect()->route($fila);
    }
}
