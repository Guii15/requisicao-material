@props(['requisicoes', 'abas' => false, 'solicitante' => false, 'vazioTitulo', 'vazioTexto', 'vazioIcone' => 'tray'])
@php
    // Dados que o Alpine usa para buscar, filtrar por aba e exportar. A tela mostra exatamente as linhas desta página.
    $linhas = $requisicoes->getCollection()->map(fn ($requisicao) => [
        'texto' => mb_strtolower($requisicao->numero.' '.$requisicao->primeiro_item.' '.$requisicao->solicitante->nome.' '.$requisicao->setor->nome),
        'grupo' => $requisicao->status->grupo(),
        'csv' => [
            $requisicao->numero,
            $requisicao->tipo->rotulo(),
            $requisicao->status->rotulo(),
            $requisicao->primeiro_item,
            $requisicao->itens_count,
            $requisicao->solicitante->nome,
            $requisicao->setor->nome,
            $requisicao->created_at->format('d/m/Y H:i'),
        ],
    ])->values()->all();
@endphp

<div x-data="listaRequisicoes(@js($linhas))">
    <div class="flex flex-col gap-3 border-b border-hairline pb-3 md:flex-row md:items-center md:justify-between">
        @if ($abas)
            <div class="flex gap-1" role="tablist" aria-label="Situação">
                @foreach (['todas' => 'Todas', 'pendentes' => 'Pendentes', 'concluidas' => 'Concluídas'] as $chave => $rotulo)
                    <button type="button" role="tab" x-on:click="aba = '{{ $chave }}'" x-bind:aria-selected="aba === '{{ $chave }}'"
                            x-bind:class="aba === '{{ $chave }}' ? 'bg-marca-suave text-marca' : 'text-mid-gray hover:bg-surface-alt hover:text-ink'"
                            class="inline-flex h-9 items-center gap-2 rounded-md px-3.5 text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-marca">
                        {{ $rotulo }}
                        @if ($chave === 'todas')
                            <span class="rounded-[4px] bg-paper px-1.5 py-px text-[11px] tabular-nums">{{ $requisicoes->total() }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        @else
            <p class="text-[13px] text-mid-gray tabular-nums">{{ $requisicoes->total() }} {{ $requisicoes->total() === 1 ? 'requisição' : 'requisições' }}</p>
        @endif

        <div class="flex items-center gap-2">
            <div class="relative min-w-0 flex-1 md:w-[260px] md:flex-none">
                <x-phosphor-magnifying-glass class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-mid-gray" aria-hidden="true" />
                <input type="search" x-model="busca" placeholder="Buscar requisição..." aria-label="Buscar requisição" class="campo h-9 pl-9 text-[13px]">
            </div>
            <button type="button" x-on:click="exportar()" title="Exportar esta lista (CSV)" aria-label="Exportar esta lista em CSV" class="botao botao-secundario size-9 px-0">
                <x-phosphor-download-simple class="size-4" aria-hidden="true" />
            </button>
        </div>
    </div>

    @if ($requisicoes->hasPages())
        <p class="mt-2 text-[12px] text-mid-gray">A busca e as abas valem para as requisições desta página.</p>
    @endif

    <div class="quadro mt-5 overflow-hidden">
        {{-- Computador e tablet: tabela. --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="tabela">
                <thead>
                    <tr>
                        <th scope="col">Número / abertura</th>
                        <th scope="col">Material</th>
                        @if ($solicitante)
                            <th scope="col">Solicitante</th>
                        @endif
                        <th scope="col">Tipo</th>
                        <th scope="col">Situação</th>
                        <th scope="col"><span class="sr-only">Abrir</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisicoes as $requisicao)
                        <tr data-href="{{ route('requisicoes.show', $requisicao) }}" x-show="aparece({{ $loop->index }})">
                            <td class="py-5">
                                <a href="{{ route('requisicoes.show', $requisicao) }}" class="font-display text-[13px] font-semibold text-ink hover:underline">{{ $requisicao->numero }}</a>
                                <span class="mt-1.5 block text-[12px] text-mid-gray tabular-nums">{{ $requisicao->created_at->format('d/m/Y · H:i') }}</span>
                            </td>
                            <td class="max-w-[22rem]">
                                <strong class="block truncate text-[13px] font-medium text-ink" title="{{ $requisicao->primeiro_item }}">{{ $requisicao->primeiro_item }}</strong>
                                @if ($requisicao->itens_count > 1)
                                    <span class="mt-1.5 block text-[12px] text-mid-gray">e mais {{ $requisicao->itens_count - 1 }} {{ $requisicao->itens_count - 1 === 1 ? 'item' : 'itens' }}</span>
                                @endif
                            </td>
                            @if ($solicitante)
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <x-avatar :nome="$requisicao->solicitante->nome" class="size-[26px] bg-surface-alt text-[9px] text-mid-gray" />
                                        <div class="min-w-0">
                                            <span class="block truncate text-[13px] text-ink">{{ $requisicao->solicitante->nome }}</span>
                                            <span class="block text-[12px] text-mid-gray">{{ \App\Support\Texto::setor($requisicao->setor->nome) }}</span>
                                        </div>
                                    </div>
                                </td>
                            @endif
                            <td><x-selo-tipo :tipo="$requisicao->tipo" /></td>
                            <td>
                                <x-selo-status :status="$requisicao->status" />
                                <span class="mt-1.5 block text-[12px] text-mid-gray" title="Tempo nesta situação">há {{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}</span>
                            </td>
                            <td class="w-12 text-right">
                                <a href="{{ route('requisicoes.show', $requisicao) }}" class="inline-flex size-9 items-center justify-center rounded-md text-mid-gray hover:bg-surface-alt hover:text-ink focus-visible:outline-2 focus-visible:outline-marca" aria-label="Abrir {{ $requisicao->numero }}">
                                    <x-phosphor-caret-right class="size-4" aria-hidden="true" />
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Celular: um bloco por requisição. --}}
        <ul class="divide-y divide-hairline md:hidden">
            @foreach ($requisicoes as $requisicao)
                <li x-show="aparece({{ $loop->index }})">
                    <a href="{{ route('requisicoes.show', $requisicao) }}" class="block px-4 py-4 transition-colors hover:bg-fundo">
                        <span class="flex items-center justify-between gap-3">
                            <span class="font-display text-[13px] font-semibold text-ink">{{ $requisicao->numero }}</span>
                            <x-selo-status :status="$requisicao->status" />
                        </span>
                        <strong class="mt-2 block truncate text-[13px] font-medium text-ink">{{ $requisicao->primeiro_item }}@if ($requisicao->itens_count > 1) <span class="font-normal text-mid-gray">e mais {{ $requisicao->itens_count - 1 }}</span>@endif</strong>
                        <span class="mt-2 flex items-center justify-between gap-3 text-[12px] text-mid-gray">
                            <x-selo-tipo :tipo="$requisicao->tipo" />
                            <span class="truncate">
                                @if ($solicitante){{ $requisicao->solicitante->nome }} · @endif{{ $requisicao->created_at->format('d/m/Y') }} · há {{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div x-show="visiveis === 0" x-cloak class="px-5 py-14 text-center">
            <span class="mx-auto mb-4 grid size-14 place-items-center rounded-full bg-surface-alt text-mid-gray"><x-dynamic-component :component="'phosphor-'.$vazioIcone" class="size-6" aria-hidden="true" /></span>
            <p class="font-display text-[15px] font-bold text-ink">Nenhuma requisição encontrada</p>
            <p class="mt-2 text-[13px] text-mid-gray">Tente outro número, material ou pessoa.</p>
        </div>
    </div>

    <div class="mt-4">{{ $requisicoes->links('components.paginacao') }}</div>
</div>
