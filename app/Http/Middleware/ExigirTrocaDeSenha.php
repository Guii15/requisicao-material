<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Senha provisória (primeiro acesso ou reset pelo admin): só libera o sistema depois da troca.
 */
class ExigirTrocaDeSenha
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->deve_trocar_senha) {
            return redirect()->route('senha.edit');
        }

        return $next($request);
    }
}
