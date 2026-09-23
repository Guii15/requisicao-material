<x-layouts.app titulo="Aprovações">
    <div class="mb-4">
        <h1 class="text-lg font-semibold">Aprovações</h1>
        <p class="text-sm text-slate-600">Requisições aguardando a sua decisão, da mais antiga para a mais nova.</p>
    </div>

    @if ($requisicoes->isEmpty())
        <div class="painel px-6 py-10">
            <p class="font-medium">Nada aguardando a sua aprovação.</p>
            <p class="mt-1 max-w-prose text-sm text-slate-600">Quando alguém do setor que você aprova abrir uma requisição, ela aparece aqui.</p>
        </div>
    @else
        {{-- Celular: lista em blocos (o líder pode aprovar do celular). Computador: tabela. --}}
        <ul class="painel divide-y divide-slate-200 sm:hidden">
            @foreach ($requisicoes as $requisicao)
                <li>
                    <a href="{{ route('requisicoes.show', $requisicao) }}" class="block px-4 py-3 hover:bg-slate-50">
                        <span class="flex items-center justify-between gap-2">
                            <span class="font-mono font-medium text-blue-800">{{ $requisicao->numero }}</span>
                            <span class="text-xs text-slate-600">{{ \App\Support\Duracao::ha($requisicao->status_alterado_em) }}</span>
                        </span>
                        <span class="mt-1 block text-sm font-medium">{{ $requisicao->solicitante->nome }} <span class="font-normal text-slate-600">({{ $requisicao->setor->nome }})</span></span>
                        <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <x-selo-tipo :tipo="$requisicao->tipo" />
                            @if ($motivos[$requisicao->id] ?? null)
                                <span class="text-xs text-amber-900">{{ $motivos[$requisicao->id] }}</span>
                            @endif
                        </span>
                        <span class="mt-1.5 block truncate text-sm text-slate-700">{{ $requisicao->finalidade }}</span>
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
                        <th scope="col">Solicitante</th>
                        <th scope="col">Setor</th>
                        <th scope="col">Itens</th>
                        <th scope="col">Finalidade</th>
                        <th scope="col" class="text-right">Aguardando há</th>
                        <th scope="col"><span class="sr-only">Ação</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($requisicoes as $requisicao)
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap">
                                <a href="{{ route('requisicoes.show', $requisicao) }}" class="link font-mono">{{ $requisicao->numero }}</a>
                                @if ($motivos[$requisicao->id] ?? null)
                                    <span class="mt-0.5 block text-xs text-amber-900">{{ $motivos[$requisicao->id] }}</span>
                                @endif
                            </td>
                            <td><x-selo-tipo :tipo="$requisicao->tipo" /></td>
                            <td class="whitespace-nowrap">{{ $requisicao->solicitante->nome }}</td>
                            <td class="whitespace-nowrap">{{ $requisicao->setor->nome }}</td>
                            <td class="max-w-xs">
                                <span class="block truncate">
                                    {{ $requisicao->primeiro_item }}
                                    @if ($requisicao->itens_count > 1)
                                        <span class="text-slate-500">e mais {{ $requisicao->itens_count - 1 }}</span>
                                    @endif
                                </span>
                            </td>
                            <td class="max-w-xs"><span class="block truncate" title="{{ $requisicao->finalidade }}">{{ $requisicao->finalidade }}</span></td>
                            <td class="num whitespace-nowrap">{{ \App\Support\Duracao::curta($requisicao->status_alterado_em) }}</td>
                            <td class="text-right">
                                <a href="{{ route('requisicoes.show', $requisicao) }}" class="botao botao-secundario h-8 px-2.5">Analisar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $requisicoes->links('components.paginacao') }}</div>
    @endif
</x-layouts.app>
