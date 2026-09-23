<x-layouts.app titulo="Minhas requisições">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold">Minhas requisições</h1>
            <p class="text-sm text-slate-600">Tudo o que você pediu ao estoque, da mais recente para a mais antiga.</p>
        </div>
        <a href="{{ route('requisicoes.create') }}" class="botao botao-primario">
            <x-phosphor-plus-bold class="size-4" aria-hidden="true" />
            Nova requisição
        </a>
    </div>

    @if ($requisicoes->isEmpty())
        <div class="painel px-6 py-10">
            <p class="font-medium">Nenhuma requisição aberta por você.</p>
            <p class="mt-1 max-w-prose text-sm text-slate-600">
                Quando precisar de material do estoque, abra uma requisição. Ela vai para a aprovação do seu setor
                e só depois aparece para o estoque separar.
            </p>
        </div>
    @else
        {{-- Celular: lista em blocos. Computador: tabela. --}}
        <ul class="painel divide-y divide-slate-200 sm:hidden">
            @foreach ($requisicoes as $requisicao)
                <li>
                    <a href="{{ route('requisicoes.show', $requisicao) }}" class="block px-4 py-3 hover:bg-slate-50">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-mono font-medium text-blue-800">{{ $requisicao->numero }}</span>
                            <span class="text-xs text-slate-600 tabular-nums">{{ $requisicao->created_at->format('d/m/Y') }}</span>
                        </span>
                        <span class="mt-1.5 flex flex-wrap gap-1.5">
                            <x-selo-tipo :tipo="$requisicao->tipo" />
                            <x-selo-status :status="$requisicao->status" />
                        </span>
                        <span class="mt-1.5 block truncate text-sm">
                            {{ $requisicao->primeiro_item }}
                            @if ($requisicao->itens_count > 1)
                                <span class="text-slate-500">e mais {{ $requisicao->itens_count - 1 }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="painel hidden overflow-x-auto sm:block">
            <table class="tabela">
                <thead>
                    <tr>
                        <th scope="col">Número</th>
                        <th scope="col">Tipo</th>
                        <th scope="col">Status</th>
                        <th scope="col">Itens</th>
                        <th scope="col">Aberta em</th>
                        <th scope="col" class="text-right">No status há</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisicoes as $requisicao)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap">
                                <a href="{{ route('requisicoes.show', $requisicao) }}" class="link font-mono">{{ $requisicao->numero }}</a>
                            </td>
                            <td><x-selo-tipo :tipo="$requisicao->tipo" /></td>
                            <td><x-selo-status :status="$requisicao->status" /></td>
                            <td class="max-w-md">
                                <span class="block truncate">
                                    {{ $requisicao->primeiro_item }}
                                    @if ($requisicao->itens_count > 1)
                                        <span class="text-slate-500">e mais {{ $requisicao->itens_count - 1 }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="whitespace-nowrap tabular-nums">{{ $requisicao->created_at->format('d/m/Y H:i') }}</td>
                            <td class="num whitespace-nowrap">{{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $requisicoes->links('components.paginacao') }}</div>
    @endif
</x-layouts.app>
