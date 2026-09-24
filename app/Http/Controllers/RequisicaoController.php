<?php

namespace App\Http\Controllers;

use App\Enums\StatusRequisicao;
use App\Models\Parametro;
use App\Models\Requisicao;
use App\Models\RequisicaoItem;
use App\Models\User;
use App\Services\FichaDaRequisicao;
use App\Services\RequisicaoWorkflow;
use App\Support\DiasUteis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function show(Request $request, Requisicao $requisicao, FichaDaRequisicao $ficha): View
    {
        Gate::authorize('view', $requisicao);

        return view('requisicoes.show', $ficha->dados($requisicao) + [
            'voltar' => $this->voltarPara($request->user(), $requisicao),
        ]);
    }

    /**
     * Cópia em PDF para imprimir ou mostrar no estoque. Abre no navegador (inline);
     * o próprio leitor de PDF oferece imprimir e salvar.
     */
    public function pdf(Request $request, Requisicao $requisicao, FichaDaRequisicao $ficha): Response
    {
        Gate::authorize('view', $requisicao);

        return $ficha->pdf($requisicao, $request->user())->stream("{$requisicao->numero}.pdf");
    }

    public function cancelar(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->cancelar($requisicao, $request->user(), $this->texto($request, 'motivo'));

        return redirect($this->voltarPara($request->user(), $requisicao)['url'])
            ->with('sucesso', "Requisição {$requisicao->numero} cancelada.");
    }

    /**
     * O próprio solicitante confirmando que o material chegou às mãos dele (Teste). Não muda
     * status, só assina — por isso volta pro detalhe, não pra uma lista.
     */
    public function confirmarRecebimento(Request $request, Requisicao $requisicao, RequisicaoWorkflow $workflow): RedirectResponse
    {
        $workflow->confirmarRecebimento($requisicao, $request->user(), $this->texto($request, 'senha'));

        return redirect()->route('requisicoes.show', $requisicao)->with('sucesso', 'Recebimento confirmado.');
    }

    /**
     * Lista de onde a pessoa veio: a própria requisição volta para "Minhas requisições";
     * a de outra pessoa, para a fila de aprovação de quem tem uma.
     *
     * @return array{url: string, rotulo: string, secao: string}
     */
    /**
     * Cada fila tem sua tela: se quem está olhando pode agir na fila do status atual da
     * requisição, "voltar" leva pra lá. Senão, cai em "Minhas requisições".
     */
    private function voltarPara(User $user, Requisicao $requisicao): array
    {
        if ($requisicao->solicitante_id !== $user->id) {
            $filas = [
                StatusRequisicao::AGUARDANDO_APROVACAO->value => ['acessar-aprovacoes', 'aprovacoes.index', 'Aprovações', 'aprovacoes'],
                StatusRequisicao::APROVADA->value => ['acessar-separacao', 'separacao.index', 'Separação', 'separacao'],
                StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE->value => ['acessar-liberacao', 'liberacao.index', 'Liberação', 'liberacao'],
                StatusRequisicao::PRONTA_PARA_RETIRADA->value => ['acessar-entrega', 'entrega.index', 'Entrega', 'entrega'],
                StatusRequisicao::EM_POSSE->value => ['acessar-devolucao', 'devolucao.index', 'Devolução', 'devolucao'],
                StatusRequisicao::AGUARDANDO_BAIXA->value => ['acessar-baixa', 'baixa.index', 'Baixa', 'baixa'],
            ];

            [$gate, $rota, $rotulo, $secao] = $filas[$requisicao->status->value] ?? [null, null, null, null];

            if ($gate !== null && Gate::forUser($user)->allows($gate)) {
                return ['url' => route($rota), 'rotulo' => $rotulo, 'secao' => $secao];
            }
        }

        return ['url' => route('requisicoes.index'), 'rotulo' => 'Minhas requisições', 'secao' => 'minhas'];
    }
}
