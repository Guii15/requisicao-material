<x-layouts.app titulo="Painel">
    <h1 class="titulo-pagina mb-6">Painel</h1>

    {{-- Tira de indicadores: sem cartão, só linhas finas separando cada número (como uma barra de status). --}}
    <div class="mb-8 grid grid-cols-2 divide-x divide-y divide-hairline border border-hairline lg:grid-cols-4 lg:divide-y-0">
        @foreach ($kpis as $kpi)
            <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                <div class="min-w-0">
                    <p class="legenda">{{ $kpi['rotulo'] }}</p>
                    <p class="mt-1 text-2xl font-semibold tracking-tight tabular-nums text-ink"
                       x-data="contador({ ate: {{ $kpi['valor'] }} })" x-text="valor">0</p>
                </div>
                <span class="shrink-0" style="color: {{ $kpi['cor'] }}">
                    <x-dynamic-component :component="'phosphor-'.$kpi['icone']" class="size-5" aria-hidden="true" />
                </span>
            </div>
        @endforeach
    </div>

    <div class="grid gap-x-8 gap-y-8 lg:grid-cols-3">
        <section>
            <h2 class="titulo-secao">Por setor</h2>
            <x-barra-ranking :itens="$porSetor" />
        </section>

        <section>
            <h2 class="titulo-secao">Por situação</h2>
            <x-barra-ranking :itens="$porSituacao" />
        </section>

        <section>
            <h2 class="titulo-secao">Quem mais solicita</h2>
            <x-barra-ranking :itens="$porSolicitante" />
        </section>
    </div>

    <section class="mt-8">
        <h2 class="titulo-secao">Histórico geral</h2>
        <p class="mb-4 text-[13px] text-mid-gray">Clique num nome, setor ou produto de qualquer linha pra ver só aquilo.</p>

        @php
            $link = fn (array $parametros) => request()->fullUrlWithQuery($parametros);
            $temFiltro = $filtro['pessoa'] || $filtro['setor'] || $filtro['produto'];
            $rotuloData = function (string $data) {
                $dia = \Illuminate\Support\Carbon::parse($data);

                return match (true) {
                    $dia->isToday() => 'Hoje',
                    $dia->isYesterday() => 'Ontem',
                    default => $dia->translatedFormat('d \d\e F'),
                };
            };
        @endphp

        @if ($temFiltro)
            <p class="mb-4 text-sm">
                <span class="text-mid-gray">Filtrando por</span>
                @if ($filtro['pessoa'])
                    <a href="{{ $link(['usuario_id' => null]) }}" class="link">{{ $filtro['pessoa'] }} ✕</a>
                @endif
                @if ($filtro['setor'])
                    <a href="{{ $link(['setor_id' => null]) }}" class="link">{{ $filtro['setor'] }} ✕</a>
                @endif
                @if ($filtro['produto'])
                    <a href="{{ $link(['produto' => null]) }}" class="link">{{ $filtro['produto'] }} ✕</a>
                @endif
                <a href="{{ route('painel.index') }}" class="text-mid-gray hover:underline">· limpar tudo</a>
            </p>
        @endif

        @if ($historico->isEmpty())
            <p class="text-sm text-mid-gray">Nenhuma movimentação encontrada.</p>
        @else
            @foreach ($historico as $dia => $eventosDoDia)
                <div class="mt-5 first:mt-0">
                    <h3 class="mb-1.5 text-[13px] font-semibold tracking-wide text-mid-gray uppercase">{{ $rotuloData($dia) }}</h3>
                    <ol class="divide-y divide-hairline">
                        @foreach ($eventosDoDia as $evento)
                            <li class="py-2.5 text-sm">
                                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                    <p class="text-ink-soft">
                                        <span class="font-medium text-ink">{{ $evento->acao->rotulo() }}</span>
                                        <span class="text-mid-gray">·</span>
                                        <a href="{{ $link(['usuario_id' => $evento->user_id]) }}" class="hover:underline">{{ $evento->usuario?->nome ?? 'Sistema' }}</a>
                                        @if ($evento->requisicao)
                                            <span class="text-mid-gray">·</span>
                                            <a href="{{ $link(['setor_id' => $evento->requisicao->setor_id]) }}" class="hover:underline">{{ $evento->requisicao->setor->nome }}</a>
                                            @if ($evento->produto)
                                                <span class="text-mid-gray">·</span>
                                                <a href="{{ $link(['produto' => $evento->produto]) }}" class="hover:underline">{{ $evento->produto }}</a>
                                            @endif
                                            <span class="text-mid-gray">·</span>
                                            <a href="{{ route('requisicoes.show', $evento->requisicao) }}" class="link font-mono">{{ $evento->requisicao->numero }}</a>
                                        @endif
                                    </p>
                                    <span class="shrink-0 text-[13px] text-mid-gray tabular-nums">{{ $evento->created_at->format('H:i') }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        @endif
    </section>
</x-layouts.app>
