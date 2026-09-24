<?php

namespace App\Http\Controllers;

use App\Models\Requisicao;
use App\Models\RequisicaoItem;
use App\Services\FilaDeBaixa;
use App\Services\FilaDeDevolucao;
use App\Services\FilaDeEntrega;
use App\Services\FilaDeLiberacao;
use App\Services\FilaDeSeparacao;
use App\Services\RequisicaoWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Telas do setor Estoque: uma fila por etapa (separação, liberação, entrega, devolução,
 * baixa). Cada índice segue o mesmo formato de aprovacoes/index — lista + link pro detalhe,
 * onde ficam os botões de ação de verdade (a Policy é quem decide, aqui é só a lista).
 */
class EstoqueController extends Controller
{
    /**
     * Monta a query padrão de uma fila: primeiro item, contagem de itens, solicitante e setor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Requisicao>  $query
     */
    private function paginar($query, int $porPagina = 50)
    {
        return $query
            ->select('requisicoes.*')
            ->addSelect(['primeiro_item' => RequisicaoItem::query()
                ->select('descricao')
                ->whereColumn('requisicao_id', 'requisicoes.id')
                ->orderBy('id')
                ->limit(1)])
            ->withCount('itens')
            ->with(['solicitante', 'setor'])
            ->orderBy('id')
            ->paginate($porPagina);
    }

    public function separacao(Request $request, FilaDeSeparacao $fila): View
    {
        Gate::authorize('acessar-separacao');

        return view('estoque.separacao', ['requisicoes' => $this->paginar($fila->para($request->user()))]);
    }

    public function separar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $itens = collect($request->input('itens', []))
            ->filter(fn (mixed $valor, mixed $id) => is_numeric($id))
            ->all();

        $workflow->separar($requisicao, $request->user(), $itens, $this->texto($request, 'senha'));

        return redirect()->route('separacao.index')->with('sucesso', "Requisição {$requisicao->numero} separada.");
    }

    public function liberacao(Request $request, FilaDeLiberacao $fila): View
    {
        Gate::authorize('acessar-liberacao');

        return view('estoque.liberacao', ['requisicoes' => $this->paginar($fila->para($request->user()))]);
    }

    public function liberar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->liberar($requisicao, $request->user(), $this->texto($request, 'senha'));

        return redirect()->route('liberacao.index')->with('sucesso', "Requisição {$requisicao->numero} liberada.");
    }

    public function reprovarEstoque(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->reprovarEstoque($requisicao, $request->user(), $this->texto($request, 'motivo'), $this->texto($request, 'senha'));

        return redirect()->route('liberacao.index')->with('sucesso', "Requisição {$requisicao->numero} reprovada pelo estoque.");
    }

    public function entrega(Request $request, FilaDeEntrega $fila): View
    {
        Gate::authorize('acessar-entrega');

        return view('estoque.entrega', ['requisicoes' => $this->paginar($fila->para($request->user()))]);
    }

    public function entregar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->entregar(
            $requisicao,
            $request->user(),
            $this->texto($request, 'retirado_por_nome') ?? '',
            $this->texto($request, 'assinatura') ?? '',
            $this->texto($request, 'senha'),
        );

        return redirect()->route('entrega.index')->with('sucesso', "Requisição {$requisicao->numero} entregue.");
    }

    public function devolucao(Request $request, FilaDeDevolucao $fila): View
    {
        Gate::authorize('acessar-devolucao');

        return view('estoque.devolucao', ['requisicoes' => $this->paginar($fila->para($request->user()))]);
    }

    public function devolver(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $itens = collect($request->input('itens', []))
            ->filter(fn (mixed $valor, mixed $id) => is_numeric($id))
            ->all();

        $workflow->devolver($requisicao, $request->user(), $itens, $this->texto($request, 'senha'));

        return redirect()->route('devolucao.index')->with('sucesso', "Devolução da requisição {$requisicao->numero} conferida.");
    }

    public function baixa(Request $request, FilaDeBaixa $fila): View
    {
        Gate::authorize('acessar-baixa');

        return view('estoque.baixa', ['requisicoes' => $this->paginar($fila->para($request->user()))]);
    }

    public function darBaixa(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->darBaixa(
            $requisicao,
            $request->user(),
            $this->texto($request, 'documento') ?? '',
            $this->texto($request, 'observacao'),
            $this->texto($request, 'senha'),
        );

        return redirect()->route('baixa.index')->with('sucesso', "Baixa da requisição {$requisicao->numero} registrada.");
    }
}
