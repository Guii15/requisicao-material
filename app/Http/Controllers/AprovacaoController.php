<?php

namespace App\Http\Controllers;

use App\Models\Requisicao;
use App\Models\RequisicaoItem;
use App\Services\FilaDeAprovacao;
use App\Services\RequisicaoWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AprovacaoController extends Controller
{
    public function index(Request $request, FilaDeAprovacao $fila): View
    {
        Gate::authorize('acessar-aprovacoes');

        $requisicoes = $fila->para($request->user())
            ->select('requisicoes.*')
            ->addSelect(['primeiro_item' => RequisicaoItem::query()
                ->select('descricao')
                ->whereColumn('requisicao_id', 'requisicoes.id')
                ->orderBy('id')
                ->limit(1)])
            ->withCount('itens')
            ->with(['solicitante', 'setor'])
            ->orderBy('id')
            ->paginate(50);

        $motivos = $request->user()->is_admin
            ? $requisicoes->getCollection()->mapWithKeys(fn (Requisicao $r) => [$r->id => $fila->motivoFilaAdmin($r)])->all()
            : [];

        return view('aprovacoes.index', ['requisicoes' => $requisicoes, 'motivos' => $motivos]);
    }

    public function aprovar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->aprovar($requisicao, $request->user(), $this->texto($request, 'senha'));

        return redirect()
            ->route('aprovacoes.index')
            ->with('sucesso', "Requisição {$requisicao->numero} aprovada. Ela segue para a separação no estoque.");
    }

    public function reprovar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->reprovar($requisicao, $request->user(), $this->texto($request, 'motivo'), $this->texto($request, 'senha'));

        return redirect()
            ->route('aprovacoes.index')
            ->with('sucesso', "Requisição {$requisicao->numero} reprovada.");
    }
}
