<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\RequisicaoItem;
use App\Services\FilaDeEnvio;
use App\Services\FilaDeRecebimento;
use App\Services\FilaDeTransferencia;
use App\Services\RequisicaoWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Transferência de filial depois da aprovação do setor: a fila de quem transfere no WinThor
 * (Isabelly) e a fila de recebimento dos líderes do estoque. Mesmo formato das outras filas.
 */
class TransferenciaController extends Controller
{
    /**
     * @param  Builder<Requisicao>  $query
     */
    private function paginar($query)
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
            ->paginate(50);
    }

    public function transferencias(Request $request, FilaDeTransferencia $fila): View
    {
        Gate::authorize('acessar-transferencias');

        return view('estoque.transferencias', ['requisicoes' => $this->paginar($fila->para($request->user()))]);
    }

    public function transferir(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->transferir($requisicao, $request->user(), $this->texto($request, 'observacao'));

        return $this->depoisDeAssinar($request, $requisicao, 'transferencias.index')->with('sucesso', "Requisição {$requisicao->numero} transferida no WinThor. Agora o estoque envia o produto.");
    }

    public function envio(Request $request, FilaDeEnvio $fila): View
    {
        Gate::authorize('acessar-envio');

        return view('estoque.envio', ['requisicoes' => $this->paginar($fila->para($request->user()))]);
    }

    public function enviar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->enviarTransferencia($requisicao, $request->user(), $request->file('foto'), $this->texto($request, 'observacao'));

        return $this->depoisDeAssinar($request, $requisicao, 'envio.index')->with('sucesso', $requisicao->status === StatusRequisicao::AGUARDANDO_TRANSFERENCIA
            ? "Produto da requisição {$requisicao->numero} enviado. Falta marcarem a transferência no WinThor; depois quem pediu confirma o recebimento."
            : "Produto da requisição {$requisicao->numero} enviado. Agora quem pediu confirma o recebimento.");
    }

    public function recebimento(Request $request, FilaDeRecebimento $fila): View
    {
        Gate::authorize('acessar-recebimento');

        return view('estoque.recebimento', ['requisicoes' => $this->paginar($fila->para($request->user()))]);
    }

    public function receber(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->receberTransferencia($requisicao, $request->user(), $request->file('foto'), $this->texto($request, 'observacao'));

        return $this->depoisDeAssinar($request, $requisicao, 'recebimento.index')->with('sucesso', "Recebimento da requisição {$requisicao->numero} confirmado.");
    }

    public function recusarRecebimento(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->recusarRecebimento($requisicao, $request->user(), $this->texto($request, 'motivo'));

        return $this->depoisDeAssinar($request, $requisicao, 'recebimento.index')->with('sucesso', "Recebimento da requisição {$requisicao->numero} recusado.");
    }
}
