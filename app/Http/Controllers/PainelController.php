<?php

namespace App\Http\Controllers;

use App\Enums\AcaoEvento;
use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\RequisicaoEvento;
use App\Models\RequisicaoItem;
use App\Models\Setor;
use App\Models\User;
use App\Services\RequisicoesVisiveis;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Painel geral: os números são da empresa toda (só contagens agregadas, qualquer usuário
 * autenticado pode ver); a atividade recente mostra só as requisições que a pessoa pode abrir.
 */
class PainelController extends Controller
{
    public function __construct(private readonly RequisicoesVisiveis $visiveis) {}

    public function index(Request $request): View
    {
        $porGrupo = $this->contagemPorGrupoDeStatus();

        return view('painel.index', [
            'kpis' => [
                ['rotulo' => 'Total de requisições', 'valor' => array_sum($porGrupo), 'icone' => 'stack', 'cor' => '#171717'],
                ['rotulo' => 'Aguardando aprovação', 'valor' => $porGrupo['aguardando'] ?? 0, 'icone' => 'clock', 'cor' => '#f59e0b'],
                ['rotulo' => 'Em andamento', 'valor' => $porGrupo['andamento'] ?? 0, 'icone' => 'arrows-clockwise', 'cor' => '#1d4ed8'],
                ['rotulo' => 'Concluídas', 'valor' => $porGrupo['concluida'] ?? 0, 'icone' => 'check-circle', 'cor' => '#059669'],
            ],
            'porSetor' => $this->porSetor(),
            'porSituacao' => $this->porSituacao($porGrupo),
            'porSolicitante' => $this->porSolicitante(),
            'historico' => $this->historicoGeral($request),
            'filtro' => $this->filtroAtivo($request),
            'meses' => $this->mesesParaFiltro(),
            'mes' => $this->mesEscolhido($request),
        ]);
    }

    /**
     * Uma linha por requisição, ordenada pela última alteração feita por uma pessoa: quando algo
     * muda, a requisição sobe para o topo (não vira uma linha nova por passo). O avanço automático
     * não conta, porque é só a passagem de um status para o outro.
     *
     * Só entra o que a pessoa pode abrir (RequisicoesVisiveis): sem isso, qualquer um via o
     * produto e o nome da compra pessoal de um colega.
     *
     * Não tem formulário de filtro: a pessoa clica no nome, no setor ou no produto que já
     * aparece numa linha pra ver só aquilo.
     *
     * @return LengthAwarePaginator<int, Requisicao>
     */
    private function historicoGeral(Request $request)
    {
        $ultimas = RequisicaoEvento::query()
            ->selectRaw('requisicao_id, max(id) as ultimo_id')
            ->where('acao', '!=', AcaoEvento::AVANCO_AUTOMATICO->value)
            ->groupBy('requisicao_id');

        $paginado = $this->visiveis->para($request->user())
            ->joinSub($ultimas, 'ultimas', 'ultimas.requisicao_id', '=', 'requisicoes.id')
            ->join('requisicao_eventos as ultimo', 'ultimo.id', '=', 'ultimas.ultimo_id')
            ->select('requisicoes.*', 'ultimas.ultimo_id as ultimo_evento_id', 'ultimo.created_at as ultima_alteracao_em')
            ->addSelect(['produto' => RequisicaoItem::query()
                ->select('descricao')
                ->whereColumn('requisicao_id', 'requisicoes.id')
                ->orderBy('id')
                ->limit(1)])
            ->withCount('itens')
            ->with('setor')
            ->when($request->filled('usuario_id'), fn (Builder $q) => $q->where('ultimo.user_id', $request->integer('usuario_id')))
            ->when($request->filled('setor_id'), fn (Builder $q) => $q->where('requisicoes.setor_id', $request->integer('setor_id')))
            ->when($request->filled('produto'), fn (Builder $q) => $q->whereHas(
                'itens', fn (Builder $i) => $i->where('descricao', $request->string('produto')),
            ))
            ->when($this->mesEscolhido($request), function (Builder $q, string $mes) {
                $inicio = Carbon::createFromFormat('Y-m-d', $mes.'-01')->startOfMonth();

                $q->whereBetween('ultimo.created_at', [$inicio, $inicio->copy()->endOfMonth()]);
            })
            ->orderByDesc('ultimo.id')
            ->paginate(20)
            ->withQueryString();

        $eventos = RequisicaoEvento::query()
            ->with('usuario')
            ->whereIn('id', $paginado->getCollection()->pluck('ultimo_evento_id'))
            ->get()
            ->keyBy('id');

        $paginado->getCollection()->each(
            fn (Requisicao $r) => $r->setRelation('ultimoEvento', $eventos->get($r->ultimo_evento_id)),
        );

        return $paginado;
    }

    /**
     * Mês marcado no filtro ("2026-09"), ou null para todos. Valor fora do formato é ignorado.
     */
    private function mesEscolhido(Request $request): ?string
    {
        $mes = $request->string('mes')->trim()->value();

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) === 1 ? $mes : null;
    }

    /**
     * Os últimos 12 meses (o atual primeiro) para o filtro da atividade.
     *
     * @return list<array{valor: string, rotulo: string}>
     */
    private function mesesParaFiltro(): array
    {
        $atual = now()->startOfMonth();

        return collect(range(0, 11))
            ->map(function (int $atras) use ($atual) {
                $mes = $atual->copy()->subMonthsNoOverflow($atras);

                return [
                    'valor' => $mes->format('Y-m'),
                    'rotulo' => ucfirst($mes->locale('pt_BR')->translatedFormat('F \d\e Y')),
                ];
            })
            ->all();
    }

    /**
     * Nome pra mostrar no "Filtrando por" — só busca o que está realmente marcado na URL.
     *
     * @return array{pessoa: ?string, setor: ?string, produto: ?string}
     */
    private function filtroAtivo(Request $request): array
    {
        return [
            'pessoa' => $request->filled('usuario_id') ? User::find($request->integer('usuario_id'))?->nome : null,
            'setor' => $request->filled('setor_id') ? Setor::find($request->integer('setor_id'))?->nome : null,
            'produto' => $request->string('produto')->trim()->value() ?: null,
        ];
    }

    /**
     * Contagem de requisições por status, agrupada pelo mesmo critério do selo de
     * situação (StatusRequisicao::grupo()) — senão os 15 status viram ilegíveis.
     *
     * @return array<string, int>
     */
    private function contagemPorGrupoDeStatus(): array
    {
        return Requisicao::query()
            ->selectRaw('status, count(*) as valor')
            ->groupBy('status')
            ->pluck('valor', 'status')
            ->reduce(function (array $porGrupo, int $valor, string $status) {
                $grupo = StatusRequisicao::from($status)->grupo();
                $porGrupo[$grupo] = ($porGrupo[$grupo] ?? 0) + $valor;

                return $porGrupo;
            }, []);
    }

    /**
     * @return list<array{rotulo: string, valor: int}>
     */
    private function porSetor(): array
    {
        return Requisicao::query()
            ->join('setores', 'setores.id', '=', 'requisicoes.setor_id')
            ->selectRaw('setores.nome as rotulo, count(*) as valor')
            ->groupBy('setores.id', 'setores.nome')
            ->orderByDesc('valor')
            ->get()
            ->map(fn ($linha) => ['rotulo' => $linha->rotulo, 'valor' => (int) $linha->valor])
            ->all();
    }

    /**
     * @param  array<string, int>  $porGrupo
     * @return list<array{rotulo: string, valor: int, cor: string}>
     */
    private function porSituacao(array $porGrupo): array
    {
        $rotulos = [
            'aguardando' => 'Aguardando',
            'andamento' => 'Em andamento',
            'concluida' => 'Concluída',
            'encerrada' => 'Encerrada',
            'alerta' => 'Com pendência',
        ];

        $cores = [
            'aguardando' => '#f59e0b',
            'andamento' => '#1d4ed8',
            'concluida' => '#059669',
            'encerrada' => '#dc2626',
            'alerta' => '#dc2626',
        ];

        arsort($porGrupo);

        return collect($porGrupo)
            ->map(fn (int $valor, string $grupo) => [
                'rotulo' => $rotulos[$grupo] ?? $grupo,
                'valor' => $valor,
                'cor' => $cores[$grupo] ?? '#171717',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{rotulo: string, valor: int}>
     */
    private function porSolicitante(): array
    {
        return Requisicao::query()
            ->join('users', 'users.id', '=', 'requisicoes.solicitante_id')
            ->selectRaw('users.nome as rotulo, count(*) as valor')
            ->groupBy('users.id', 'users.nome')
            ->orderByDesc('valor')
            ->limit(8)
            ->get()
            ->map(fn ($linha) => ['rotulo' => $linha->rotulo, 'valor' => (int) $linha->valor])
            ->all();
    }
}
