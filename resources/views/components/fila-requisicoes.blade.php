@props(['titulo', 'icone', 'requisicoes', 'contador', 'vazioTitulo', 'vazioTexto', 'colunaHa' => 'Há'])
{{-- Lista padrão de fila do estoque: mesma tabela achatada das outras listas, um título e um vazio próprios. --}}
<x-layouts.app :titulo="$titulo">
    <div class="mb-5 flex items-baseline gap-3">
        <h1 class="titulo-pagina">{{ $titulo }}</h1>
        @if ($requisicoes->total() > 0)
            <span class="text-sm text-mid-gray tabular-nums">{{ $contador }}</span>
        @endif
    </div>

    @if ($requisicoes->isEmpty())
        <x-vazio :icone="$icone" :titulo="$vazioTitulo">
            {{ $vazioTexto }}
        </x-vazio>
    @else
        <ul class="quadro divide-y divide-hairline overflow-hidden lg:hidden">
            @foreach ($requisicoes as $requisicao)
                <li>
                    <a href="{{ route('requisicoes.show', $requisicao) }}" class="block px-4 py-3 transition-colors hover:bg-surface-alt">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-mono text-sm font-medium text-ink">{{ $requisicao->numero }}</span>
                            <span class="text-xs text-mid-gray">{{ \App\Support\Duracao::ha($requisicao->status_alterado_em) }}</span>
                        </span>
                        <span class="mt-1 block text-sm">
                            <span class="font-medium">{{ $requisicao->solicitante->nome }}</span>
                            <span class="text-mid-gray">· {{ $requisicao->setor->nome }}</span>
                        </span>
                        <span class="mt-1.5 flex items-center gap-2 text-sm">
                            <x-selo-tipo :tipo="$requisicao->tipo" />
                            <span class="truncate text-ink-soft">
                                {{ $requisicao->primeiro_item }}
                                @if ($requisicao->itens_count > 1)
                                    <span class="text-mid-gray">e mais {{ $requisicao->itens_count - 1 }}</span>
                                @endif
                            </span>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="hidden lg:block">
            <table class="tabela table-fixed">
                <colgroup>
                    <col class="w-40 xl:w-44">
                    <col class="w-36">
                    <col class="w-36 xl:w-52">
                    <col class="w-32">
                    <col>
                    <col class="w-32">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Número</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Solicitante</th>
                        <th scope="col">Setor</th>
                        <th scope="col">Itens</th>
                        <th scope="col" class="text-right">{{ $colunaHa }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisicoes as $requisicao)
                        <tr data-href="{{ route('requisicoes.show', $requisicao) }}">
                            <td><a href="{{ route('requisicoes.show', $requisicao) }}" class="link font-mono">{{ $requisicao->numero }}</a></td>
                            <td><x-selo-tipo :tipo="$requisicao->tipo" /></td>
                            <td><span class="block truncate" title="{{ $requisicao->solicitante->nome }}">{{ $requisicao->solicitante->nome }}</span></td>
                            <td><span class="block truncate">{{ $requisicao->setor->nome }}</span></td>
                            <td>
                                <span class="block truncate" title="{{ $requisicao->primeiro_item }}">
                                    {{ $requisicao->primeiro_item }}
                                    @if ($requisicao->itens_count > 1)
                                        <span class="text-mid-gray">e mais {{ $requisicao->itens_count - 1 }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="num text-mid-gray">{{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $requisicoes->links('components.paginacao') }}</div>
    @endif
</x-layouts.app>
