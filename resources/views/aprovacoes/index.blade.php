<x-layouts.app titulo="Aprovações">
    <div class="mb-5 flex items-baseline gap-3">
        <h1 class="titulo-pagina">Aprovações</h1>
        @if ($requisicoes->total() > 0)
            <span class="text-sm text-mid-gray tabular-nums">{{ $requisicoes->total() }} aguardando a sua decisão</span>
        @endif
    </div>

    @if ($requisicoes->isEmpty())
        <x-vazio icone="check-circle" titulo="Nada aguardando a sua aprovação">
            Quando alguém do seu setor pedir material, a requisição aparece aqui.
        </x-vazio>
    @else
        {{-- Celular e tablet: blocos (o líder pode aprovar pelo celular). Computador: tabela de colunas fixas. --}}
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
                        @if ($motivos[$requisicao->id] ?? null)
                            <span class="mt-0.5 block text-xs font-medium text-amber-700">{{ $motivos[$requisicao->id] }}</span>
                        @endif
                        <span class="mt-1.5 flex items-center gap-2 text-sm">
                            <x-selo-tipo :tipo="$requisicao->tipo" />
                            <span class="truncate text-ink-soft">
                                {{ $requisicao->primeiro_item }}
                                @if ($requisicao->itens_count > 1)
                                    <span class="text-mid-gray">e mais {{ $requisicao->itens_count - 1 }}</span>
                                @endif
                            </span>
                        </span>
                        <span class="mt-1 line-clamp-2 text-sm text-mid-gray">{{ $requisicao->finalidade }}</span>
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
                        <th scope="col">Finalidade</th>
                        <th scope="col" class="text-right">Aguardando</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisicoes as $requisicao)
                        <tr data-href="{{ route('requisicoes.show', $requisicao) }}">
                            <td>
                                <a href="{{ route('requisicoes.show', $requisicao) }}" class="link font-mono">{{ $requisicao->numero }}</a>
                                @if ($motivos[$requisicao->id] ?? null)
                                    <span class="mt-0.5 block text-xs font-medium text-amber-700">{{ $motivos[$requisicao->id] }}</span>
                                @endif
                            </td>
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
                            <td class="text-mid-gray"><span class="line-clamp-2" title="{{ $requisicao->finalidade }}">{{ $requisicao->finalidade }}</span></td>
                            <td class="num text-mid-gray">{{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $requisicoes->links('components.paginacao') }}</div>
    @endif
</x-layouts.app>
