<x-layouts.app titulo="Visão geral">
    @php
        $primeiroNome = explode(' ', trim(auth()->user()->nome))[0];
        $tonsKpi = [
            ['tile' => 'bg-marca text-white', 'barra' => 'bg-marca', 'nota' => 'Todas as solicitações'],
            ['tile' => 'bg-aviso text-white', 'barra' => 'bg-aviso', 'nota' => 'Precisam de atenção'],
            ['tile' => 'bg-info text-white', 'barra' => 'bg-info', 'nota' => 'No fluxo do estoque'],
            ['tile' => 'bg-sucesso text-white', 'barra' => 'bg-sucesso', 'nota' => 'Finalizadas'],
        ];
        $paleta = ['var(--color-marca)', 'var(--color-info)', 'var(--color-aviso)', 'var(--color-sucesso)', 'var(--color-mid-gray)'];
        $avatarRanking = ['bg-info-suave text-info', 'bg-sucesso-suave text-sucesso', 'bg-aviso-suave text-aviso'];

        $aguardando = $kpis[1]['valor'];
        $totalSetor = collect($porSetor)->sum('valor');
        $maiorSetor = max(1, collect($porSetor)->max('valor') ?? 1);
        $totalSituacao = collect($porSituacao)->sum('valor');

        // Rosca: uma fatia por situação, com um respiro de 1,5% entre elas.
        $partes = [];
        $acumulado = 0;
        foreach ($porSituacao as $item) {
            $fim = $acumulado + ($totalSituacao > 0 ? $item['valor'] / $totalSituacao * 100 : 0);
            $corteFatia = max($acumulado, $fim - 1.5);
            $partes[] = $item['cor'].' '.round($acumulado, 2).'% '.round($corteFatia, 2).'%';
            $partes[] = 'var(--color-paper) '.round($corteFatia, 2).'% '.round($fim, 2).'%';
            $acumulado = $fim;
        }
        $rosca = $totalSituacao > 0 ? 'conic-gradient('.implode(', ', $partes).')' : 'conic-gradient(var(--color-hairline) 0 100%)';

        $link = fn (array $parametros) => request()->fullUrlWithQuery($parametros);
        $temFiltro = $filtro['pessoa'] || $filtro['setor'] || $filtro['produto'] || $mes;
        $eventos = $historico->getCollection();
        $rotuloData = function (string $data) {
            $dia = \Illuminate\Support\Carbon::parse($data);

            return match (true) {
                $dia->isToday() => 'Hoje',
                $dia->isYesterday() => 'Ontem',
                default => $dia->translatedFormat('j \d\e F'),
            };
        };
    @endphp

    {{-- Faixa de boas-vindas no preto da marca --}}
    <section class="relative mb-5 overflow-hidden rounded-xl bg-gradient-to-br from-marca-escura via-marca to-marca px-6 py-6 text-white lg:px-8 lg:py-8">
        <div class="pointer-events-none absolute -top-28 -right-10 size-80 rounded-full bg-marca-clara/40 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 right-48 size-64 rounded-full bg-white/10 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-5">
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-[11px] font-semibold tracking-[0.08em] text-white/80 uppercase">
                    <span class="size-1.5 rounded-full bg-white" aria-hidden="true"></span>Gestão de materiais · {{ now()->translatedFormat('j \d\e F') }}
                </p>
                <h1 class="mt-3 font-display text-[28px] leading-tight font-bold lg:text-[32px]">Olá, {{ $primeiroNome }}</h1>
                <p class="mt-1.5 text-[13px] text-white/80">Acompanhe o que acontece com os materiais.</p>
                @if ($aguardando > 0)
                    @can('acessar-aprovacoes')
                        <a href="{{ route('aprovacoes.index') }}" class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-[12px] font-medium text-white ring-1 ring-white/15 transition-colors hover:bg-white/15">
                            <span class="size-1.5 rounded-full bg-aviso" aria-hidden="true"></span>
                            {{ $aguardando }} {{ $aguardando === 1 ? 'requisição aguarda' : 'requisições aguardam' }} o próximo passo
                            <x-phosphor-arrow-right class="size-3.5" aria-hidden="true" />
                        </a>
                    @else
                        <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-[12px] font-medium ring-1 ring-white/15">
                            <span class="size-1.5 rounded-full bg-aviso" aria-hidden="true"></span>
                            {{ $aguardando }} {{ $aguardando === 1 ? 'requisição aguarda' : 'requisições aguardam' }} o próximo passo
                        </p>
                    @endcan
                @endif
            </div>
            <a href="{{ route('requisicoes.create') }}" class="botao h-10 bg-white px-5 text-marca shadow-[0_8px_24px_-12px_rgba(0,0,0,0.5)] hover:bg-marca-suave"><x-phosphor-plus class="size-4" aria-hidden="true" />Nova requisição</a>
        </div>
    </section>

    {{-- Totais --}}
    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        @foreach ($kpis as $kpi)
            <div class="quadro relative overflow-hidden px-4 pt-5 pb-4 transition-shadow hover:shadow-[0_8px_24px_-12px_rgba(0,0,0,0.18)] lg:px-6 lg:pt-6 lg:pb-5">
                <span class="absolute inset-x-0 top-0 h-[3px] {{ $tonsKpi[$loop->index]['barra'] }}" aria-hidden="true"></span>
                <div class="flex items-center justify-between gap-2 text-[12px] text-mid-gray">
                    <dt>{{ $kpi['rotulo'] }}</dt>
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg shadow-sm {{ $tonsKpi[$loop->index]['tile'] }}">
                        <x-dynamic-component :component="'phosphor-'.$kpi['icone']" class="size-[18px]" aria-hidden="true" />
                    </span>
                </div>
                <dd class="mt-4 mb-4 block font-display text-[32px] leading-none font-semibold tabular-nums lg:text-[40px]">{{ str_pad((string) $kpi['valor'], 2, '0', STR_PAD_LEFT) }}</dd>
                <p class="flex items-center justify-between gap-2 text-[11px] text-mid-gray">
                    {{ $tonsKpi[$loop->index]['nota'] }}
                    @if ($loop->index === 1 && $kpi['valor'] > 0)
                        <span class="hidden rounded-[3px] bg-aviso-suave px-1.5 py-0.5 text-[10px] text-aviso sm:inline">Pendente</span>
                    @endif
                </p>
            </div>
        @endforeach
    </dl>

    <div class="mb-5"></div>

    {{-- Três recortes: setor, situação e quem mais pede --}}
    <div class="quadro mb-7 grid lg:grid-cols-[1fr_1fr_1.1fr]">
        <section class="min-w-0 p-5 lg:p-6">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-[14px] font-bold text-ink">Requisições por setor</h2>
                <span class="text-[11px] text-mid-gray">{{ $totalSetor }} no total</span>
            </div>
            <p class="mt-1.5 text-[12px] text-mid-gray">Distribuição das solicitações</p>
            <ol class="mt-6 space-y-5">
                @forelse ($porSetor as $item)
                    <li>
                        <div class="mb-2 flex items-center justify-between gap-3 text-[12px]">
                            <span class="flex min-w-0 items-center gap-2"><i class="size-2 shrink-0 rounded-full" style="background: {{ $paleta[$loop->index % count($paleta)] }}" aria-hidden="true"></i><span class="truncate text-ink">{{ \App\Support\Texto::setor($item['rotulo']) }}</span></span>
                            <strong class="font-semibold tabular-nums">{{ $item['valor'] }}</strong>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-surface-alt"><div class="h-full rounded-full" style="width: {{ round($item['valor'] / $maiorSetor * 100) }}%; background: {{ $paleta[$loop->index % count($paleta)] }}"></div></div>
                    </li>
                @empty
                    <li class="text-[12px] text-mid-gray">Sem dados ainda.</li>
                @endforelse
            </ol>
        </section>

        <section class="min-w-0 border-t border-hairline p-5 lg:border-t-0 lg:border-l lg:p-6">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-[14px] font-bold text-ink">Por situação</h2>
                <span class="text-[11px] text-mid-gray">{{ $totalSituacao }} {{ $totalSituacao === 1 ? 'requisição' : 'requisições' }}</span>
            </div>
            <p class="mt-1.5 text-[12px] text-mid-gray">Cada etapa, em um só lugar</p>
            <div class="mt-7 flex items-center gap-5">
                <div class="grid size-[132px] shrink-0 -rotate-90 place-items-center rounded-full" style="background: {{ $rosca }}" role="img" aria-label="{{ $totalSituacao }} requisições por situação">
                    <div class="flex size-[98px] rotate-90 flex-col items-center justify-center rounded-full bg-paper">
                        <strong class="font-display text-[30px] leading-none font-bold tabular-nums">{{ $totalSituacao }}</strong>
                        <span class="mt-1 text-[10px] text-mid-gray">{{ $totalSituacao === 1 ? 'requisição' : 'requisições' }}</span>
                    </div>
                </div>
                <ul class="min-w-0 flex-1 space-y-3.5">
                    @forelse ($porSituacao as $item)
                        <li class="flex items-center justify-between gap-2 text-[12px]">
                            <span class="flex min-w-0 items-center gap-2"><i class="size-2 shrink-0 rounded-full" style="background: {{ $item['cor'] }}" aria-hidden="true"></i><span class="truncate text-ink">{{ $item['rotulo'] }}</span></span>
                            <strong class="font-semibold tabular-nums">{{ $item['valor'] }}</strong>
                        </li>
                    @empty
                        <li class="text-[12px] text-mid-gray">Sem dados ainda.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <section class="min-w-0 border-t border-hairline p-5 lg:border-t-0 lg:border-l lg:p-6">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-[14px] font-bold text-ink">Quem mais solicita</h2>
                <span class="text-[11px] text-mid-gray">Por pessoa</span>
            </div>
            <p class="mt-1.5 text-[12px] text-mid-gray">Solicitantes mais ativos</p>
            <ol class="mt-4">
                @forelse ($porSolicitante as $item)
                    <li class="flex items-center gap-2.5 rounded-md px-1.5 py-2 text-[12px] transition-colors hover:bg-fundo">
                        <span class="grid size-5 shrink-0 place-items-center rounded-full text-[10px] font-bold tabular-nums {{ $loop->first ? 'bg-marca text-white' : 'bg-surface-alt text-mid-gray' }}">{{ $loop->iteration }}</span>
                        <x-avatar :nome="$item['rotulo']" class="size-[26px] text-[9px] {{ $avatarRanking[$loop->index] ?? 'bg-surface-alt text-mid-gray' }}" />
                        <span class="min-w-0 flex-1 truncate text-ink">{{ $item['rotulo'] }}</span>
                        <strong class="rounded-full bg-marca-suave px-2 py-0.5 text-[11px] font-semibold text-marca tabular-nums">{{ $item['valor'] }}</strong>
                    </li>
                @empty
                    <li class="text-[12px] text-mid-gray">Sem dados ainda.</li>
                @endforelse
            </ol>
        </section>
    </div>

    {{-- Atividade recente --}}
    <section class="quadro overflow-hidden" x-data="{ busca: '' }">
        <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between lg:px-6">
            <div>
                <h2 class="text-[14px] font-bold text-ink">Atividade recente</h2>
                <p class="mt-1 text-[12px] text-mid-gray">Histórico de movimentações dos materiais. Clique num nome, setor ou produto para ver só aquilo.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <form method="GET" action="{{ route('painel.index') }}">
                    @foreach (['usuario_id', 'setor_id', 'produto'] as $parametro)
                        @if (request()->filled($parametro))
                            <input type="hidden" name="{{ $parametro }}" value="{{ request($parametro) }}">
                        @endif
                    @endforeach
                    <label for="filtro-mes" class="sr-only">Filtrar por mês</label>
                    <select id="filtro-mes" name="mes" x-on:change="$el.form.submit()" class="campo h-9 py-0 text-[13px] sm:w-[190px]">
                        <option value="">Todos os meses</option>
                        @foreach ($meses as $opcao)
                            <option value="{{ $opcao['valor'] }}" @selected($mes === $opcao['valor'])>{{ $opcao['rotulo'] }}</option>
                        @endforeach
                    </select>
                </form>
                <div class="relative sm:w-[240px]">
                    <x-phosphor-magnifying-glass class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-mid-gray" aria-hidden="true" />
                    <input type="search" x-model="busca" placeholder="Buscar no histórico..." aria-label="Buscar no histórico" class="campo h-9 pl-9 text-[13px]">
                </div>
            </div>
        </div>

        @if ($temFiltro)
            <p class="flex flex-wrap items-center gap-2 border-t border-hairline px-5 py-3 text-[13px] lg:px-6">
                <span class="text-mid-gray">Filtrando por</span>
                @if ($filtro['pessoa'])
                    <a href="{{ $link(['usuario_id' => null]) }}" class="selo selo-contorno hover:bg-fundo">{{ $filtro['pessoa'] }} <x-phosphor-x class="size-3" aria-label="Remover filtro" /></a>
                @endif
                @if ($filtro['setor'])
                    <a href="{{ $link(['setor_id' => null]) }}" class="selo selo-contorno hover:bg-fundo">{{ $filtro['setor'] }} <x-phosphor-x class="size-3" aria-label="Remover filtro" /></a>
                @endif
                @if ($filtro['produto'])
                    <a href="{{ $link(['produto' => null]) }}" class="selo selo-contorno hover:bg-fundo">{{ $filtro['produto'] }} <x-phosphor-x class="size-3" aria-label="Remover filtro" /></a>
                @endif
                @if ($mes)
                    <a href="{{ $link(['mes' => null]) }}" class="selo selo-contorno hover:bg-fundo">{{ collect($meses)->firstWhere('valor', $mes)['rotulo'] ?? $mes }} <x-phosphor-x class="size-3" aria-label="Remover filtro" /></a>
                @endif
                <a href="{{ route('painel.index') }}" class="text-mid-gray hover:text-ink hover:underline">Limpar tudo</a>
            </p>
        @endif

        @if ($eventos->isEmpty())
            <div class="border-t border-hairline px-5 py-14 text-center">
                <p class="font-display text-[15px] font-bold text-ink">Nenhuma movimentação encontrada</p>
                <p class="mt-2 text-[13px] text-mid-gray">Quando houver requisições, cada passo aparece aqui.</p>
            </div>
        @else
            {{-- Computador e tablet: tabela. --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col" class="lg:pl-6">Evento</th>
                            <th scope="col">Produto</th>
                            <th scope="col">Responsável</th>
                            <th scope="col">Setor</th>
                            <th scope="col">Requisição</th>
                            <th scope="col" class="lg:pr-6">Horário</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($eventos as $evento)
                            @php($textoBusca = mb_strtolower($evento->acao->rotulo().' '.($evento->produto ?? '').' '.($evento->usuario?->nome ?? 'Sistema').' '.($evento->requisicao?->setor->nome ?? '').' '.($evento->requisicao?->numero ?? '')))
                            <tr x-show="@js($textoBusca).includes(busca.trim().toLowerCase())">
                                <td class="lg:pl-6">
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid size-[30px] shrink-0 place-items-center rounded-lg bg-marca-suave text-marca"><x-phosphor-arrow-right class="size-4" aria-hidden="true" /></span>
                                        <div>
                                            <strong class="block text-[13px] font-medium text-ink">{{ $evento->acao->rotulo() }}</strong>
                                            <small class="mt-1 block text-[12px] text-mid-gray">{{ $rotuloData($evento->created_at->toDateString()) }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td class="max-w-[16rem]">
                                    @if ($evento->requisicao && $evento->produto)
                                        <a href="{{ $link(['produto' => $evento->produto]) }}" class="block truncate text-ink-soft hover:text-ink hover:underline" title="{{ $evento->produto }}">{{ $evento->produto }}</a>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ $link(['usuario_id' => $evento->user_id]) }}" class="flex items-center gap-2 whitespace-nowrap text-ink-soft hover:text-ink hover:underline">
                                        <x-avatar :nome="$evento->usuario?->nome ?? 'Sistema'" class="size-[23px] bg-surface-alt text-[8px] text-mid-gray" />
                                        {{ $evento->usuario?->nome ?? 'Sistema' }}
                                    </a>
                                </td>
                                <td>
                                    @if ($evento->requisicao)
                                        <a href="{{ $link(['setor_id' => $evento->requisicao->setor_id]) }}" class="rounded-[3px] bg-surface-alt px-1.5 py-1 text-[11px] text-mid-gray hover:text-ink">{{ $evento->requisicao->setor->nome }}</a>
                                    @endif
                                </td>
                                <td>
                                    @if ($evento->requisicao)
                                        <a href="{{ route('requisicoes.show', $evento->requisicao) }}" class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-ink hover:underline">{{ $evento->requisicao->numero }}<x-phosphor-arrow-up-right class="size-3" aria-hidden="true" /></a>
                                    @endif
                                </td>
                                <td class="text-mid-gray tabular-nums lg:pr-6">{{ $evento->created_at->format('H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Celular: uma linha corrida por evento. --}}
            <ol class="divide-y divide-hairline border-t border-hairline md:hidden">
                @foreach ($eventos as $evento)
                    @php($textoBusca = mb_strtolower($evento->acao->rotulo().' '.($evento->produto ?? '').' '.($evento->usuario?->nome ?? 'Sistema').' '.($evento->requisicao?->setor->nome ?? '').' '.($evento->requisicao?->numero ?? '')))
                    <li class="px-5 py-3.5" x-show="@js($textoBusca).includes(busca.trim().toLowerCase())">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="font-medium text-ink">{{ $evento->acao->rotulo() }}</span>
                            <span class="shrink-0 text-[12px] text-mid-gray tabular-nums">{{ $rotuloData($evento->created_at->toDateString()) }} · {{ $evento->created_at->format('H:i') }}</span>
                        </div>
                        <p class="mt-1 text-[12px] text-mid-gray">
                            <a href="{{ $link(['usuario_id' => $evento->user_id]) }}" class="text-ink-soft hover:underline">{{ $evento->usuario?->nome ?? 'Sistema' }}</a>
                            @if ($evento->requisicao)
                                em <a href="{{ $link(['setor_id' => $evento->requisicao->setor_id]) }}" class="text-ink-soft hover:underline">{{ $evento->requisicao->setor->nome }}</a>
                                @if ($evento->produto)
                                    <br><a href="{{ $link(['produto' => $evento->produto]) }}" class="text-ink-soft hover:underline">{{ $evento->produto }}</a>
                                @endif
                                <br><a href="{{ route('requisicoes.show', $evento->requisicao) }}" class="link text-[12px]">{{ $evento->requisicao->numero }}</a>
                            @endif
                        </p>
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($historico->hasPages())
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-hairline px-5 py-3 text-[12px] text-mid-gray lg:px-6">
                <span>Mostrando {{ $historico->firstItem() }}–{{ $historico->lastItem() }} de {{ $historico->total() }} movimentações</span>
                <div class="flex items-center gap-2">
                    @if ($historico->onFirstPage())
                        <span class="botao botao-secundario h-8 opacity-50"><x-phosphor-caret-left class="size-3.5" aria-hidden="true" />Anterior</span>
                    @else
                        <a href="{{ $historico->previousPageUrl() }}" class="botao botao-secundario h-8"><x-phosphor-caret-left class="size-3.5" aria-hidden="true" />Anterior</a>
                    @endif
                    <span class="px-1 tabular-nums">Página {{ $historico->currentPage() }} de {{ $historico->lastPage() }}</span>
                    @if ($historico->hasMorePages())
                        <a href="{{ $historico->nextPageUrl() }}" class="botao botao-secundario h-8">Próxima<x-phosphor-caret-right class="size-3.5" aria-hidden="true" /></a>
                    @else
                        <span class="botao botao-secundario h-8 opacity-50">Próxima<x-phosphor-caret-right class="size-3.5" aria-hidden="true" /></span>
                    @endif
                </div>
            </div>
        @endif

        <div class="flex items-center justify-between gap-3 border-t border-hairline px-5 py-3 text-[12px] text-mid-gray lg:px-6">
            <span>Movimentações mais recentes primeiro</span>
            <a href="{{ route('requisicoes.index') }}" class="inline-flex items-center gap-1.5 font-medium text-ink hover:underline">Ver minhas requisições <x-phosphor-arrow-right class="size-3.5" aria-hidden="true" /></a>
        </div>
    </section>
</x-layouts.app>
