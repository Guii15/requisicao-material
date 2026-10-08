<x-layouts.app titulo="Painel">
    <h1 class="titulo-pagina mb-7">Painel</h1>

    {{-- Quatro totais em linha, sem cartão nem ícone: o número é a informação. --}}
    <dl class="mb-12 grid grid-cols-2 gap-x-8 gap-y-6 lg:grid-cols-4 lg:gap-x-0 lg:divide-x lg:divide-hairline">
        @foreach ($kpis as $kpi)
            <div class="lg:px-8 lg:first:pl-0">
                <dt class="text-[13px] text-mid-gray">{{ $kpi['rotulo'] }}</dt>
                <dd class="mt-1 text-[34px] leading-10 font-semibold tracking-[-0.045em] tabular-nums text-ink">{{ $kpi['valor'] }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="grid gap-x-12 gap-y-10 lg:grid-cols-3">
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

    <section class="mt-14">
        <h2 class="titulo-secao">Histórico geral</h2>
        <p class="mb-5 max-w-prose text-[13px] text-mid-gray">Clique num nome, setor ou produto de qualquer linha pra ver só aquilo.</p>

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
            <p class="mb-5 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-mid-gray">Filtrando por</span>
                @if ($filtro['pessoa'])
                    <a href="{{ $link(['usuario_id' => null]) }}" class="selo selo-contorno hover:bg-fundo">{{ $filtro['pessoa'] }} ✕</a>
                @endif
                @if ($filtro['setor'])
                    <a href="{{ $link(['setor_id' => null]) }}" class="selo selo-contorno hover:bg-fundo">{{ $filtro['setor'] }} ✕</a>
                @endif
                @if ($filtro['produto'])
                    <a href="{{ $link(['produto' => null]) }}" class="selo selo-contorno hover:bg-fundo">{{ $filtro['produto'] }} ✕</a>
                @endif
                <a href="{{ route('painel.index') }}" class="text-mid-gray hover:text-ink hover:underline">Limpar tudo</a>
            </p>
        @endif

        @if ($historico->isEmpty())
            <x-vazio titulo="Nenhuma movimentação encontrada">
                Quando houver requisições, cada passo aparece aqui.
            </x-vazio>
        @else
            {{-- Celular e tablet: uma linha corrida por evento. Computador: tabela de colunas. --}}
            <div class="lg:hidden">
                @foreach ($historico as $dia => $eventosDoDia)
                    <div class="mt-6 first:mt-0">
                        <h3 class="mb-1 text-[13px] font-medium text-mid-gray">{{ $rotuloData($dia) }}</h3>
                        <ol class="divide-y divide-hairline">
                            @foreach ($eventosDoDia as $evento)
                                <li class="py-3">
                                    <div class="flex items-baseline justify-between gap-3">
                                        <span class="font-medium text-ink">{{ $evento->acao->rotulo() }}</span>
                                        <span class="shrink-0 text-[13px] text-mid-gray tabular-nums">{{ $evento->created_at->format('H:i') }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[13px] text-mid-gray">
                                        <a href="{{ $link(['usuario_id' => $evento->user_id]) }}" class="text-ink-soft hover:underline">{{ $evento->usuario?->nome ?? 'Sistema' }}</a>
                                        @if ($evento->requisicao)
                                            em <a href="{{ $link(['setor_id' => $evento->requisicao->setor_id]) }}" class="text-ink-soft hover:underline">{{ $evento->requisicao->setor->nome }}</a>
                                            @if ($evento->produto)
                                                <br><a href="{{ $link(['produto' => $evento->produto]) }}" class="text-ink-soft hover:underline">{{ $evento->produto }}</a>
                                            @endif
                                            <br><a href="{{ route('requisicoes.show', $evento->requisicao) }}" class="link font-mono text-[12px]">{{ $evento->requisicao->numero }}</a>
                                        @endif
                                    </p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>

            <table class="tabela hidden table-fixed lg:table">
                <colgroup>
                    <col class="w-20">
                    <col class="w-64">
                    <col class="w-52">
                    <col class="w-36">
                    <col>
                    <col class="w-44">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Hora</th>
                        <th scope="col">Passo</th>
                        <th scope="col">Quem</th>
                        <th scope="col">Setor</th>
                        <th scope="col">Produto</th>
                        <th scope="col">Requisição</th>
                    </tr>
                </thead>
                @foreach ($historico as $dia => $eventosDoDia)
                    <tbody>
                        <tr>
                            <th colspan="6" scope="colgroup" class="pt-6 pb-2 text-[13px] font-semibold text-ink">{{ $rotuloData($dia) }}</th>
                        </tr>
                        @foreach ($eventosDoDia as $evento)
                            <tr>
                                <td class="text-mid-gray tabular-nums">{{ $evento->created_at->format('H:i') }}</td>
                                <td class="font-medium text-ink">{{ $evento->acao->rotulo() }}</td>
                                <td><a href="{{ $link(['usuario_id' => $evento->user_id]) }}" class="block truncate text-ink-soft hover:text-ink hover:underline">{{ $evento->usuario?->nome ?? 'Sistema' }}</a></td>
                                @if ($evento->requisicao)
                                    <td><a href="{{ $link(['setor_id' => $evento->requisicao->setor_id]) }}" class="block truncate text-ink-soft hover:text-ink hover:underline">{{ $evento->requisicao->setor->nome }}</a></td>
                                    <td>
                                        @if ($evento->produto)
                                            <a href="{{ $link(['produto' => $evento->produto]) }}" class="block truncate text-ink-soft hover:text-ink hover:underline" title="{{ $evento->produto }}">{{ $evento->produto }}</a>
                                        @endif
                                    </td>
                                    <td><a href="{{ route('requisicoes.show', $evento->requisicao) }}" class="link font-mono text-[13px]">{{ $evento->requisicao->numero }}</a></td>
                                @else
                                    <td></td><td></td><td></td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        @endif
    </section>
</x-layouts.app>
