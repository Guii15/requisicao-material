<x-layouts.app titulo="Minhas requisições">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Minhas requisições</h1>
            @if ($requisicoes->total() > 0)
                <p class="mt-1 text-sm text-slate-500">{{ $requisicoes->total() }} {{ $requisicoes->total() === 1 ? 'requisição' : 'requisições' }}</p>
            @endif
        </div>
        <a href="{{ route('requisicoes.create') }}" class="botao botao-primario"><x-phosphor-plus class="size-4" aria-hidden="true" />Nova requisição</a>
    </div>

    @if ($requisicoes->isEmpty())
        <x-vazio icone="package" titulo="Você ainda não abriu nenhuma requisição">
            Quando precisar de material do estoque, é por aqui que você pede.
        </x-vazio>
    @else
        {{-- Celular e tablet: blocos. Computador: tabela de colunas fixas, sem rolagem lateral. --}}
        <ul class="quadro divide-y divide-slate-100 overflow-hidden lg:hidden">
            @foreach ($requisicoes as $requisicao)
                <li>
                    <a href="{{ route('requisicoes.show', $requisicao) }}" class="block px-4 py-3.5 transition-colors hover:bg-slate-50">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-mono text-sm font-medium text-marinho-800">{{ $requisicao->numero }}</span>
                            <span class="text-xs text-slate-500 tabular-nums">{{ $requisicao->created_at->format('d/m/Y') }}</span>
                        </span>
                        <span class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
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
                            <td class="text-slate-600 tabular-nums">{{ $requisicao->created_at->format('d/m/Y H:i') }}</td>
                            <td class="num text-slate-500" title="Tempo na situação atual">{{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $requisicoes->links('components.paginacao') }}</div>
    @endif
</x-layouts.app>
