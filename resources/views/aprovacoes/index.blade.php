<x-layouts.app titulo="Aprovações">
    <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
        <h1 class="text-xl font-semibold">Aprovações</h1>
        @if ($requisicoes->total() > 0)
            <p class="text-sm text-slate-600">{{ $requisicoes->total() }} aguardando</p>
        @endif
    </div>

    @if ($requisicoes->isEmpty())
        <div class="quadro px-4 py-6 text-sm">
            <p class="font-medium">Nada aguardando a sua aprovação.</p>
        </div>
    @else
        {{-- Celular e tablet: blocos (o líder pode aprovar pelo celular). Computador: tabela de colunas fixas. --}}
        <ul class="quadro divide-y divide-slate-300 lg:hidden">
            @foreach ($requisicoes as $requisicao)
                <li>
                    <a href="{{ route('requisicoes.show', $requisicao) }}" class="block px-4 py-3 hover:bg-marinho-50">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-mono font-medium text-blue-800">{{ $requisicao->numero }}</span>
                            <span class="text-xs text-slate-600">{{ \App\Support\Duracao::ha($requisicao->status_alterado_em) }}</span>
                        </span>
                        <span class="mt-1 block text-sm">
                            <span class="font-medium">{{ $requisicao->solicitante->nome }}</span>
                            <span class="text-slate-600">· {{ $requisicao->setor->nome }}</span>
                        </span>
                        @if ($motivos[$requisicao->id] ?? null)
                            <span class="mt-0.5 block text-xs text-amber-900">{{ $motivos[$requisicao->id] }}</span>
                        @endif
                        <span class="mt-1.5 flex items-center gap-2 text-sm">
                            <x-selo-tipo :tipo="$requisicao->tipo" />
                            <span class="truncate text-slate-700">
                                {{ $requisicao->primeiro_item }}
                                @if ($requisicao->itens_count > 1)
                                    <span class="text-slate-500">e mais {{ $requisicao->itens_count - 1 }}</span>
                                @endif
                            </span>
                        </span>
                        <span class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $requisicao->finalidade }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="quadro hidden lg:block">
            <table class="tabela table-fixed">
                <colgroup>
                    <col class="w-40 xl:w-44">
                    <col class="w-36">
                    <col class="w-36 xl:w-52">
                    <col class="w-28">
                    <col>
                    <col>
                    <col class="w-28">
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
                                    <span class="mt-0.5 block text-xs text-amber-900">{{ $motivos[$requisicao->id] }}</span>
                                @endif
                            </td>
                            <td><x-selo-tipo :tipo="$requisicao->tipo" /></td>
                            <td><span class="block truncate" title="{{ $requisicao->solicitante->nome }}">{{ $requisicao->solicitante->nome }}</span></td>
                            <td><span class="block truncate">{{ $requisicao->setor->nome }}</span></td>
                            <td>
                                <span class="block truncate" title="{{ $requisicao->primeiro_item }}">
                                    {{ $requisicao->primeiro_item }}
                                    @if ($requisicao->itens_count > 1)
                                        <span class="text-slate-500">e mais {{ $requisicao->itens_count - 1 }}</span>
                                    @endif
                                </span>
                            </td>
                            <td><span class="line-clamp-2" title="{{ $requisicao->finalidade }}">{{ $requisicao->finalidade }}</span></td>
                            <td class="num">{{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $requisicoes->links('components.paginacao') }}</div>
    @endif
</x-layouts.app>
