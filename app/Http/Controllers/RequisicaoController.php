<?php

namespace App\Http\Controllers;

use App\Models\Parametro;
use App\Models\Requisicao;
use App\Models\RequisicaoItem;
use App\Services\AssinaturaService;
use App\Services\FilaDeAprovacao;
use App\Services\RequisicaoWorkflow;
use App\Support\DiasUteis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RequisicaoController extends Controller
{
    public function index(Request $request): View
    {
        $requisicoes = Requisicao::query()
            ->where('solicitante_id', $request->user()->id)
            ->select('requisicoes.*')
            ->addSelect(['primeiro_item' => RequisicaoItem::query()
                ->select('descricao')
                ->whereColumn('requisicao_id', 'requisicoes.id')
                ->orderBy('id')
                ->limit(1)])
            ->withCount('itens')
            ->orderByDesc('id')
            ->paginate(25);

        return view('requisicoes.index', ['requisicoes' => $requisicoes]);
    }

    public function create(): View
    {
        Gate::authorize('create', Requisicao::class);

        $prazoDias = Parametro::inteiro('prazo_max_devolucao_dias');

        return view('requisicoes.create', [
            'prazoDias' => $prazoDias,
            'limiteDevolucao' => DiasUteis::doBanco()->somar(now(), $prazoDias),
        ]);
    }

    public function store(Request $request, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $requisicao = $workflow->criar(
            $request->user(),
            $request->only(['tipo', 'itens', 'justificativa', 'finalidade', 'data_prevista_devolucao']),
            $this->texto($request, 'senha'),
        );

        return redirect()
            ->route('requisicoes.show', $requisicao)
            ->with('sucesso', "Requisição {$requisicao->numero} assinada e enviada para aprovação.");
    }

    public function show(Requisicao $requisicao, AssinaturaService $assinaturas, FilaDeAprovacao $fila): View
    {
        Gate::authorize('view', $requisicao);

        $requisicao->load(['itens', 'assinaturas', 'eventos.usuario', 'solicitante', 'setor', 'reprovadoPor', 'canceladoPor']);

        return view('requisicoes.show', [
            'requisicao' => $requisicao,
            'verificacao' => $assinaturas->verificar($requisicao),
            'motivoSemAprovador' => $fila->motivoSemAprovador($requisicao),
        ]);
    }

    public function cancelar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->cancelar($requisicao, $request->user(), $this->texto($request, 'motivo'));

        return redirect()
            ->route('requisicoes.show', $requisicao)
            ->with('sucesso', "Requisição {$requisicao->numero} cancelada.");
    }
}
