<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
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

        // Só o líder do estoque recebe pedido de outro setor; ele precisa saber por quê.
        $motivos = $request->user()->is_lider_estoque
            ? $requisicoes->getCollection()->mapWithKeys(fn (Requisicao $r) => [$r->id => $fila->motivoSemAprovador($r)])->all()
            : [];

        return view('aprovacoes.index', ['requisicoes' => $requisicoes, 'motivos' => $motivos]);
    }

    public function aprovar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->aprovar($requisicao, $request->user(), $this->texto($request, 'senha'), $this->recusasDoFormulario($request));

        // Recusar todos os itens reprova a requisição inteira.
        if ($requisicao->status === StatusRequisicao::REPROVADA) {
            return redirect()
                ->route('aprovacoes.index')
                ->with('sucesso', "Requisição {$requisicao->numero} reprovada: todos os itens foram recusados.");
        }

        $destino = $requisicao->tipo === TipoRequisicao::COMPRA_FUNCIONARIO
            ? 'a decisão da Erica'
            : 'a separação no estoque';

        return redirect()
            ->route('aprovacoes.index')
            ->with('sucesso', "Requisição {$requisicao->numero} aprovada. Ela segue para {$destino}.");
    }

    public function reprovar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->reprovar($requisicao, $request->user(), $this->texto($request, 'motivo'), $this->texto($request, 'senha'));

        return redirect()
            ->route('aprovacoes.index')
            ->with('sucesso', "Requisição {$requisicao->numero} reprovada.");
    }
}
