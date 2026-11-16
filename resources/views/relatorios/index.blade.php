<x-layouts.app titulo="Relatórios">
    @php
        $tipo = $filtro['tipo'];
        $base = ['de' => $filtro['de']->format('Y-m-d'), 'ate' => $filtro['ate']->format('Y-m-d'), 'setor' => $filtro['setor_id']];
        $rotulos = [
            \App\Enums\TipoRequisicao::USO_CONSUMO->value => 'Uso e Consumo',
            \App\Enums\TipoRequisicao::COMPRA_FUNCIONARIO->value => 'Compras de funcionários',
            \App\Enums\TipoRequisicao::TESTE->value => 'Teste',
        ];
        $numericas = ['qtd', 'qtd_separada', 'devolvido_ok', 'devolvido_defeito', 'nao_devolvido'];
    @endphp

    <x-cabecalho titulo="Relatórios" subtitulo="Uma linha por item pedido. Escolha o tipo, o período e, se quiser, o setor e a situação.">
        <a href="{{ route('relatorios.exportar', request()->query() + ['tipo' => $tipo->value]) }}" class="botao botao-primario">
            <x-phosphor-microsoft-excel-logo class="size-[18px]" aria-hidden="true" />Baixar para o Excel
        </a>
    </x-cabecalho>

    <nav class="mb-5 flex flex-wrap gap-1 border-b border-hairline pb-3" aria-label="Tipo de relatório">
        @foreach ($tipos as $opcao)
            <a href="{{ route('relatorios.index', array_filter($base) + ['tipo' => $opcao->value]) }}"
               @if ($opcao === $tipo) aria-current="page" @endif
               @class([
                   'inline-flex h-9 items-center rounded-md px-3.5 text-[13px] font-medium transition-colors focus-visible:outline-2 focus-visible:outline-marca',
                   'bg-marca-suave text-marca' => $opcao === $tipo,
                   'text-mid-gray hover:bg-surface-alt hover:text-ink' => $opcao !== $tipo,
               ])>{{ $rotulos[$opcao->value] }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('relatorios.index') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <input type="hidden" name="tipo" value="{{ $tipo->value }}">
        <div>
            <label for="de" class="rotulo">De</label>
            <input type="date" id="de" name="de" value="{{ $filtro['de']->format('Y-m-d') }}" class="campo mt-1.5">
        </div>
        <div>
            <label for="ate" class="rotulo">Até</label>
            <input type="date" id="ate" name="ate" value="{{ $filtro['ate']->format('Y-m-d') }}" class="campo mt-1.5">
        </div>
        <div>
            <label for="setor" class="rotulo">Setor</label>
            <select id="setor" name="setor" class="campo mt-1.5 sm:w-56">
                <option value="">Todos</option>
                @foreach ($setores as $setor)
                    <option value="{{ $setor->id }}" @selected($filtro['setor_id'] === $setor->id)>{{ \App\Support\Texto::setor($setor->nome) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="rotulo">Situação</label>
            <select id="status" name="status" class="campo mt-1.5 sm:w-60">
                <option value="">Todas</option>
                @foreach ($statusPossiveis as $status)
                    <option value="{{ $status->value }}" @selected($filtro['status'] === $status)>{{ $status->rotulo() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="botao botao-secundario">Filtrar</button>
    </form>

    <p class="mb-3 text-[13px] text-mid-gray tabular-nums">
        {{ $totalRequisicoes }} {{ $totalRequisicoes === 1 ? 'requisição' : 'requisições' }}, {{ $itens->total() }} {{ $itens->total() === 1 ? 'item' : 'itens' }} de {{ $filtro['de']->format('d/m/Y') }} a {{ $filtro['ate']->format('d/m/Y') }}
    </p>

    <section class="quadro" aria-label="Relatório de {{ $rotulos[$tipo->value] }}">
        @if ($linhas->isEmpty())
            <p class="p-8 text-center text-sm text-mid-gray">Nenhum pedido de {{ $rotulos[$tipo->value] }} nesse período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="tabela">
                    <thead>
                        <tr>
                            @foreach ($colunas as $chave => $titulo)
                                <th scope="col" @class(['text-right' => in_array($chave, $numericas, true)])>{{ $titulo }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr>
                                @foreach ($colunas as $chave => $titulo)
                                    @if ($chave === 'numero')
                                        <td class="whitespace-nowrap font-medium">
                                            @if ($linha['pode_abrir'])
                                                <a href="{{ route('requisicoes.show', $linha['numero']) }}" class="text-ink hover:underline">{{ $linha['numero'] }}</a>
                                            @else
                                                {{ $linha['numero'] }}
                                            @endif
                                        </td>
                                    @else
                                        <td @class(['text-right tabular-nums' => in_array($chave, $numericas, true), 'whitespace-nowrap' => in_array($chave, ['data', 'baixa_em', 'compra_em', 'devolucao_prevista'], true)])>{{ $linha[$chave] !== '' ? $linha[$chave] : '—' }}</td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="mt-4">
        <x-paginacao :paginator="$itens" />
    </div>
</x-layouts.app>
