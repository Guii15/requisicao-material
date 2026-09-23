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
}
