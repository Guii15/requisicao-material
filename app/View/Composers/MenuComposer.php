<?php

namespace App\View\Composers;

use App\Services\FilaDeAprovacao;
use App\Services\FilaDeBaixa;
use App\Services\FilaDeCompras;
use App\Services\FilaDeDevolucao;
use App\Services\FilaDeEntrega;
use App\Services\FilaDeLiberacao;
use App\Services\FilaDeSeparacao;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Itens do menu que dependem do papel do usuário, com o contador de pendências.
 * Nulo = o usuário não tem aquele item no menu.
 */
class MenuComposer
{
    public function __construct(
        private readonly FilaDeAprovacao $fila,
        private readonly FilaDeSeparacao $filaSeparacao,
        private readonly FilaDeLiberacao $filaLiberacao,
        private readonly FilaDeEntrega $filaEntrega,
        private readonly FilaDeDevolucao $filaDevolucao,
        private readonly FilaDeBaixa $filaBaixa,
        private readonly FilaDeCompras $filaCompras,
    ) {}

    public function compose(View $view): void
    {
        $user = Auth::user();

        $contar = fn (string $gate, $fila) => $user !== null && Gate::forUser($user)->allows($gate)
            ? $fila->para($user)->count()
            : null;

        $view->with('menu', [
            'aprovacoes' => $contar('acessar-aprovacoes', $this->fila),
            'separacao' => $contar('acessar-separacao', $this->filaSeparacao),
            'liberacao' => $contar('acessar-liberacao', $this->filaLiberacao),
            'entrega' => $contar('acessar-entrega', $this->filaEntrega),
            'devolucao' => $contar('acessar-devolucao', $this->filaDevolucao),
            'baixa' => $contar('acessar-baixa', $this->filaBaixa),
            'compras' => $contar('acessar-compras', $this->filaCompras),
        ]);
    }
}
