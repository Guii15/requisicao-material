<?php

namespace App\View\Composers;

use App\Services\FilaDeAprovacao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Itens do menu que dependem do papel do usuário, com o contador de pendências.
 * Nulo = o usuário não tem aquele item no menu.
 */
class MenuComposer
{
    public function __construct(private readonly FilaDeAprovacao $fila) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        $view->with('menu', [
            'aprovacoes' => $user !== null && Gate::forUser($user)->allows('acessar-aprovacoes')
                ? $this->fila->para($user)->count()
                : null,
        ]);
    }
}
