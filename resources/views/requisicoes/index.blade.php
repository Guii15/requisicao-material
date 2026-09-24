<x-layouts.app titulo="Minhas requisições">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Minhas requisições</h1>
        <a href="{{ route('requisicoes.create') }}" class="botao botao-primario">Nova requisição</a>
    </div>

    @if ($requisicoes->isEmpty())
        <div class="quadro px-4 py-6 text-sm">
            <p class="font-medium">Nenhuma requisição aberta por você.</p>
            <p class="mt-1 text-slate-600">Para pedir material ao estoque, use Nova requisição.</p>
        </div>
    @else
        {{-- Celular e tablet: blocos. Computador: tabela de colunas fixas, sem rolagem lateral. --}}
        <ul class="quadro divide-y divide-slate-300 lg:hidden">
            @foreach ($requisicoes as $requisicao)
                <li>
                    <a href="{{ route('requisicoes.show', $requisicao) }}" class="block px-4 py-3 hover:bg-marinho-50">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-mono font-medium text-blue-800">{{ $requisicao->numero }}</span>
                            <span class="text-xs text-slate-600 tabular-nums">{{ $requisicao->created_at->format('d/m/Y') }}</span>
                        </span>
                        <span class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                            <x-selo-tipo :tipo="$requisicao->tipo" />
                            <x-selo-status :status="$requisicao->status" />
                        </span>
                        <span class="mt-1.5 block truncate text-sm text-slate-700">
                            {{ $requisicao->primeiro_item }}
                            @if ($requisicao->itens_count > 1)
                                <span class="text-slate-500">e mais {{ $requisicao->itens_count - 1 }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="quadro hidden lg:block">
            <table class="tabela table-fixed">
                <colgroup>
                    <col class="w-44">
                    <col class="w-40">
                    <col class="w-64">
                    <col>
                    <col class="w-40">
                    <col class="w-24">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Número</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Situação</th>
                        <th scope="col">Itens</th>
                        <th scope="col">Aberta em</th>
                        <th scope="col" class="text-right">Há</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisicoes as $requisicao)
                        <tr data-href="{{ route('requisicoes.show', $requisicao) }}">
                            <td><a href="{{ route('requisicoes.show', $requisicao) }}" class="link font-mono">{{ $requisicao->numero }}</a></td>
                            <td><x-selo-tipo :tipo="$requisicao->tipo" /></td>
                            <td><x-selo-status :status="$requisicao->status" /></td>
                            <td>
                                <span class="block truncate">
                                    {{ $requisicao->primeiro_item }}
                                    @if ($requisicao->itens_count > 1)
                                        <span class="text-slate-500">e mais {{ $requisicao->itens_count - 1 }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="tabular-nums">{{ $requisicao->created_at->format('d/m/Y H:i') }}</td>
                            <td class="num" title="Tempo na situação atual">{{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $requisicoes->links('components.paginacao') }}</div>
    @endif
</x-layouts.app>
