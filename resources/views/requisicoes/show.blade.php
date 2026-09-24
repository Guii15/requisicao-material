<x-layouts.app :titulo="$requisicao->numero" :secao="$voltar['secao']">
    @php
        $dataHora = fn ($data) => $data->format('d/m/Y').' às '.$data->format('H:i');
        // Caixas de assinatura: as feitas, na ordem em que aconteceram, e depois as que faltam.
        $blocos = $requisicao->assinaturas
            ->map(fn ($assinatura) => ['etapa' => $assinatura->etapa, 'assinatura' => $assinatura])
            ->concat(collect($pendentes)->map(fn ($etapa) => ['etapa' => $etapa, 'assinatura' => null]));
        $aguardandoAprovacao = $requisicao->status === \App\Enums\StatusRequisicao::AGUARDANDO_APROVACAO;
    @endphp

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <a href="{{ $voltar['url'] }}" data-voltar class="botao botao-secundario">Voltar para {{ $voltar['rotulo'] }}</a>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('requisicoes.pdf', $requisicao) }}" target="_blank" class="botao botao-secundario">Imprimir / PDF</a>
            @can('cancelar', $requisicao)
                <button type="button" class="botao botao-secundario" x-data x-on:click="$dispatch('abrir-modal', 'cancelar')">Cancelar requisição</button>
            @endcan
            @can('reprovar', $requisicao)
                <button type="button" class="botao botao-perigo-contorno" x-data x-on:click="$dispatch('abrir-modal', 'reprovar')">Reprovar</button>
            @endcan
            @can('aprovar', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'aprovar')">Aprovar</button>
            @endcan
        </div>
    </div>

    @unless ($verificacao->integro)
        <div role="alert" class="mb-3 border-2 border-red-700 bg-red-50 px-4 py-3 text-sm text-red-950">
            <p class="font-semibold">Documento divergente: o conteúdo não confere com as assinaturas.</p>
            <p class="mt-0.5">Não entregue material com base nesta requisição e avise a TI.</p>
            <ul class="mt-1.5 list-disc space-y-0.5 pl-5">
                @foreach ($verificacao->problemas as $problema)
                    <li>{{ $problema }}</li>
                @endforeach
            </ul>
        </div>
    @endunless

    <article class="quadro" aria-labelledby="numero-requisicao">
        <header class="flex flex-wrap border-b border-slate-400">
            <div class="flex-1 px-4 py-3">
                <span class="legenda">Requisição de material</span>
                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 id="numero-requisicao" class="font-mono text-2xl font-semibold tracking-tight">{{ $requisicao->numero }}</h1>
                    <x-selo-tipo :tipo="$requisicao->tipo" />
                </div>
            </div>
            <div class="w-full border-t border-slate-300 px-4 py-3 sm:w-auto sm:min-w-72 sm:border-t-0 sm:border-l">
                <span class="legenda">Situação</span>
                <x-selo-status :status="$requisicao->status" class="mt-1 text-base font-semibold" />
                <p class="mt-0.5 text-xs text-slate-600 tabular-nums">desde {{ $dataHora($requisicao->status_alterado_em) }}</p>
            </div>
        </header>

        <dl class="grid grid-cols-2 gap-px bg-slate-300 lg:grid-cols-4">
            <div class="bg-white px-4 py-2.5">
                <dt class="legenda">Solicitante</dt>
                <dd class="mt-0.5 text-sm font-medium">{{ $requisicao->solicitante->nome }}</dd>
            </div>
            <div class="bg-white px-4 py-2.5">
                <dt class="legenda">Setor</dt>
                <dd class="mt-0.5 text-sm font-medium">{{ $requisicao->setor->nome }}</dd>
            </div>
            <div class="bg-white px-4 py-2.5">
                <dt class="legenda">Aberta em</dt>
                <dd class="mt-0.5 text-sm tabular-nums">{{ $dataHora($requisicao->created_at) }}</dd>
            </div>
            <div class="bg-white px-4 py-2.5">
                <dt class="legenda">Devolução</dt>
                <dd class="mt-0.5 text-sm">
                    @if ($requisicao->tipo->exigeDevolucao())
                        Até <span class="font-medium tabular-nums">{{ $requisicao->data_prevista_devolucao?->format('d/m/Y') }}</span>
                    @else
                        <span class="text-slate-600">Não volta (uso e consumo)</span>
                    @endif
                </dd>
            </div>
        </dl>

        <dl class="divide-y divide-slate-300 border-t border-slate-300">
            <div class="px-4 py-2.5">
                <dt class="legenda">Finalidade</dt>
                <dd class="mt-0.5 text-sm whitespace-pre-line">{{ $requisicao->finalidade }}</dd>
            </div>
            <div class="px-4 py-2.5">
                <dt class="legenda">Justificativa</dt>
                <dd class="mt-0.5 text-sm whitespace-pre-line">{{ $requisicao->justificativa }}</dd>
            </div>
            @if ($requisicao->motivo_reprovacao)
                <div class="bg-red-50 px-4 py-2.5">
                    <dt class="legenda text-red-800">Motivo da reprovação</dt>
                    <dd class="mt-0.5 text-sm whitespace-pre-line">{{ $requisicao->motivo_reprovacao }}</dd>
                    <dd class="mt-0.5 text-xs text-slate-600">{{ $requisicao->reprovadoPor?->nome }}, {{ $dataHora($requisicao->reprovado_em) }}</dd>
                </div>
            @endif
            @if ($requisicao->motivo_cancelamento)
                <div class="bg-slate-50 px-4 py-2.5">
                    <dt class="legenda">Motivo do cancelamento</dt>
                    <dd class="mt-0.5 text-sm whitespace-pre-line">{{ $requisicao->motivo_cancelamento }}</dd>
                    <dd class="mt-0.5 text-xs text-slate-600">{{ $requisicao->canceladoPor?->nome }}, {{ $dataHora($requisicao->cancelado_em) }}</dd>
                </div>
            @endif
        </dl>

        <section aria-labelledby="itens-titulo">
            <h2 id="itens-titulo" class="quadro-titulo border-t border-slate-400">Itens ({{ $requisicao->itens->count() }})</h2>
            {{-- Celular: um item por linha, com as quantidades embaixo da descrição. --}}
            <ol class="divide-y divide-slate-200 sm:hidden">
                @foreach ($requisicao->itens as $item)
                    <li class="flex gap-3 px-4 py-2.5 text-sm">
                        <span class="w-5 shrink-0 text-right font-mono text-slate-500">{{ $loop->iteration }}</span>
                        <div class="min-w-0">
                            <p>{{ $item->descricao }}</p>
                            <p class="mt-0.5 flex flex-wrap gap-x-4 text-xs text-slate-600">
                                <span>Solicitada: <span class="font-mono text-slate-900">{{ \App\Support\Quantidade::formatar($item->qtd_solicitada) }}</span> {{ $item->unidade }}</span>
                                <span>Separada: <span class="font-mono text-slate-900">{{ \App\Support\Quantidade::formatar($item->qtd_separada) }}</span></span>
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <div class="hidden overflow-x-auto sm:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col" class="w-10 text-right">#</th>
                            <th scope="col">Descrição</th>
                            <th scope="col" class="w-20">Unidade</th>
                            <th scope="col" class="w-32 text-right">Qtd. solicitada</th>
                            <th scope="col" class="w-32 text-right">Qtd. separada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requisicao->itens as $item)
                            <tr>
                                <td class="num text-slate-500">{{ $loop->iteration }}</td>
                                <td>{{ $item->descricao }}</td>
                                <td>{{ $item->unidade }}</td>
                                <td class="num">{{ \App\Support\Quantidade::formatar($item->qtd_solicitada) }}</td>
                                <td class="num text-slate-600">{{ \App\Support\Quantidade::formatar($item->qtd_separada) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="assinaturas-titulo">
            <h2 id="assinaturas-titulo" class="quadro-titulo border-t border-slate-400">Assinaturas</h2>
            {{-- As bordas da última coluna e da última linha ficam escondidas sob a borda do quadro. --}}
            <div class="overflow-hidden">
                <ol class="-mr-px -mb-px grid sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($blocos as $bloco)
                        <li class="border-r border-b border-slate-300 px-4 py-2.5">
                            <span class="legenda">{{ $bloco['etapa']->rotulo() }}</span>
                            @if ($assinatura = $bloco['assinatura'])
                                <p class="mt-0.5 text-sm font-medium">{{ $assinatura->nome_assinante }}</p>
                                @if ($assinatura->cargo_assinante)
                                    <p class="text-xs text-slate-600">{{ $assinatura->cargo_assinante }}</p>
                                @endif
                                <p class="mt-1 flex flex-wrap gap-x-3 text-xs">
                                    <span class="text-slate-700 tabular-nums">{{ $dataHora($assinatura->assinado_em) }}</span>
                                    <span class="font-mono text-slate-900" title="Código de verificação">{{ \App\Services\AssinaturaService::codigoCurto($assinatura->hash_documento) }}</span>
                                </p>
                            @elseif ($bloco['etapa'] === \App\Enums\EtapaAssinatura::APROVACAO_SETOR && $aguardandoAprovacao)
                                <p class="mt-0.5 text-sm text-amber-900">
                                    @if ($aguardandoAprovacaoDe->isEmpty())
                                        Nenhum aprovador disponível. Procure a TI.
                                    @else
                                        Aguardando {{ $aguardandoAprovacaoDe->pluck('nome')->join(', ', ' ou ') }}
                                    @endif
                                </p>
                                @if ($motivoSemAprovador)
                                    <p class="mt-0.5 text-xs text-slate-600">Pelos líderes do estoque: {{ mb_strtolower($motivoSemAprovador) }}.</p>
                                @endif
                            @else
                                <p class="mt-0.5 text-sm text-slate-400">Pendente</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
            <p @class([
                'border-t border-slate-300 px-4 py-2 text-xs',
                'text-emerald-800' => $verificacao->integro,
                'font-semibold text-red-800' => ! $verificacao->integro,
            ])>
                @if ($verificacao->integro)
                    Assinaturas íntegras: o conteúdo confere com o que foi assinado em cada etapa.
                @else
                    Divergente: o conteúdo não confere com as assinaturas.
                @endif
            </p>
        </section>
    </article>

    <section class="quadro mt-4" aria-labelledby="historico-titulo">
        <h2 id="historico-titulo" class="quadro-titulo">Histórico</h2>
        <ol class="divide-y divide-slate-200 text-sm">
            @foreach ($requisicao->eventos as $evento)
                <li class="grid gap-x-4 px-4 py-2 sm:grid-cols-[9.5rem_13rem_minmax(0,1fr)]">
                    <span class="text-slate-600 tabular-nums">{{ $evento->created_at->format('d/m/Y H:i') }}</span>
                    <span class="font-medium">{{ $evento->acao->rotulo() }}</span>
                    <span class="text-slate-700">
                        {{ $evento->usuario?->nome }}
                        @if (is_string($evento->dados['motivo'] ?? null))
                            <span class="block text-slate-600">Motivo: {{ $evento->dados['motivo'] }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    @push('modais')
        @can('aprovar', $requisicao)
            <x-modal nome="aprovar" :titulo="'Aprovar '.$requisicao->numero" :abrir="old('_acao') === 'aprovar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.aprovar', $requisicao) }}">
                    @csrf
                    <input type="hidden" name="_acao" value="aprovar">
                    <div class="space-y-3 px-4 py-3">
                        <p class="text-sm text-slate-700">Depois de aprovada, a requisição vai para o estoque separar. Digite a sua senha para assinar a aprovação.</p>
                        <div>
                            <label for="aprovar-senha" class="rotulo">Sua senha</label>
                            <input id="aprovar-senha" name="senha" type="password" autocomplete="new-password" required data-foco
                                   class="campo mt-1.5 @error('senha') campo-erro @enderror">
                            @error('senha')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-300 bg-slate-50 px-4 py-2.5">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario">Assinar e aprovar</button>
                    </div>
                </form>
            </x-modal>

            <x-modal nome="reprovar" :titulo="'Reprovar '.$requisicao->numero" :abrir="old('_acao') === 'reprovar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.reprovar', $requisicao) }}">
                    @csrf
                    <input type="hidden" name="_acao" value="reprovar">
                    <div class="space-y-3 px-4 py-3">
                        <div>
                            <label for="reprovar-motivo" class="rotulo">Motivo da reprovação</label>
                            <p class="ajuda">O solicitante vai ver este texto.</p>
                            <textarea id="reprovar-motivo" name="motivo" rows="3" maxlength="1000" required data-foco
                                      class="campo mt-1.5 @error('motivo') campo-erro @enderror">{{ old('_acao') === 'reprovar' && is_string(old('motivo')) ? old('motivo') : '' }}</textarea>
                            @error('motivo')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="reprovar-senha" class="rotulo">Sua senha</label>
                            <input id="reprovar-senha" name="senha" type="password" autocomplete="new-password" required
                                   class="campo mt-1.5 @error('senha') campo-erro @enderror">
                            @error('senha')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-300 bg-slate-50 px-4 py-2.5">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-perigo">Assinar e reprovar</button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('cancelar', $requisicao)
            <x-modal nome="cancelar" :titulo="'Cancelar '.$requisicao->numero" :abrir="old('_acao') === 'cancelar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.cancelar', $requisicao) }}">
                    @csrf
                    <input type="hidden" name="_acao" value="cancelar">
                    <div class="space-y-3 px-4 py-3">
                        <p class="text-sm text-slate-700">A requisição é encerrada e não pode ser reaberta. Ela continua no histórico.</p>
                        <div>
                            <label for="cancelar-motivo" class="rotulo">Motivo do cancelamento</label>
                            <textarea id="cancelar-motivo" name="motivo" rows="3" maxlength="1000" required data-foco
                                      class="campo mt-1.5 @error('motivo') campo-erro @enderror">{{ old('_acao') === 'cancelar' && is_string(old('motivo')) ? old('motivo') : '' }}</textarea>
                            @error('motivo')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-300 bg-slate-50 px-4 py-2.5">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-perigo">Cancelar requisição</button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endpush
</x-layouts.app>
