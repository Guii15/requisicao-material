<x-layouts.app :titulo="$requisicao->numero" :secao="$voltar['secao']">
    @php
        $dataHora = fn ($data) => $data->format('d/m/Y').' às '.$data->format('H:i');
        // Caixas de assinatura: as feitas, na ordem em que aconteceram, e depois as que faltam.
        $blocos = $requisicao->assinaturas
            ->map(fn ($assinatura) => ['etapa' => $assinatura->etapa, 'assinatura' => $assinatura])
            ->concat(collect($pendentes)->map(fn ($etapa) => ['etapa' => $etapa, 'assinatura' => null]));
        $aguardandoAprovacao = $requisicao->status === \App\Enums\StatusRequisicao::AGUARDANDO_APROVACAO;
    @endphp

    <a href="{{ $voltar['url'] }}" data-voltar class="-ml-2 inline-flex h-8 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-200/60 hover:text-slate-900">
        <x-phosphor-arrow-left class="size-4" aria-hidden="true" />Voltar para {{ $voltar['rotulo'] }}
    </a>

    <div class="mt-3 mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 id="numero-requisicao" class="font-mono text-2xl font-semibold tracking-tight">{{ $requisicao->numero }}</h1>
                <x-selo-tipo :tipo="$requisicao->tipo" />
            </div>
            <p class="mt-1.5 flex flex-wrap items-center gap-x-2 text-sm text-slate-500">
                <x-selo-status :status="$requisicao->status" class="font-medium text-slate-900" />
                <span class="tabular-nums">desde {{ $dataHora($requisicao->status_alterado_em) }}</span>
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('requisicoes.pdf', $requisicao) }}" target="_blank" class="botao botao-secundario">
                <x-phosphor-printer class="size-[18px]" aria-hidden="true" />Imprimir / PDF
            </a>
            @can('cancelar', $requisicao)
                <button type="button" class="botao botao-secundario" x-data x-on:click="$dispatch('abrir-modal', 'cancelar')">Cancelar requisição</button>
            @endcan
            @can('reprovar', $requisicao)
                <button type="button" class="botao botao-perigo-contorno" x-data x-on:click="$dispatch('abrir-modal', 'reprovar')">Reprovar</button>
            @endcan
            @can('aprovar', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'aprovar')">
                    <x-phosphor-check class="size-[18px]" aria-hidden="true" />Aprovar
                </button>
            @endcan
        </div>
    </div>

    @unless ($verificacao->integro)
        <div role="alert" class="mb-6 flex gap-3 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-950">
            <x-phosphor-warning-octagon class="size-5 shrink-0 text-red-700" aria-hidden="true" />
            <div>
                <p class="font-semibold">Documento divergente: o conteúdo não confere com as assinaturas.</p>
                <p class="mt-0.5">Não entregue material com base nesta requisição e avise a TI.</p>
                <ul class="mt-1.5 list-disc space-y-0.5 pl-5">
                    @foreach ($verificacao->problemas as $problema)
                        <li>{{ $problema }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endunless

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start">
        <div class="space-y-6">
            <article class="quadro" aria-labelledby="numero-requisicao">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-5 p-5 lg:grid-cols-4">
                    <div>
                        <dt class="legenda">Solicitante</dt>
                        <dd class="mt-1 font-medium">{{ $requisicao->solicitante->nome }}</dd>
                    </div>
                    <div>
                        <dt class="legenda">Setor</dt>
                        <dd class="mt-1 font-medium">{{ $requisicao->setor->nome }}</dd>
                    </div>
                    <div>
                        <dt class="legenda">Aberta em</dt>
                        <dd class="mt-1 tabular-nums">{{ $dataHora($requisicao->created_at) }}</dd>
                    </div>
                    <div>
                        <dt class="legenda">Devolução</dt>
                        <dd class="mt-1">
                            @if ($requisicao->tipo->exigeDevolucao())
                                Até <span class="font-medium tabular-nums">{{ $requisicao->data_prevista_devolucao?->format('d/m/Y') }}</span>
                            @else
                                <span class="text-slate-500">Não volta (uso e consumo)</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <dl class="space-y-5 border-t border-slate-100 p-5">
                    <div>
                        <dt class="legenda">Finalidade</dt>
                        <dd class="mt-1 whitespace-pre-line">{{ $requisicao->finalidade }}</dd>
                    </div>
                    <div>
                        <dt class="legenda">Justificativa</dt>
                        <dd class="mt-1 whitespace-pre-line">{{ $requisicao->justificativa }}</dd>
                    </div>
                    @if ($requisicao->motivo_reprovacao)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                            <dt class="text-[13px] font-medium text-red-800">Motivo da reprovação</dt>
                            <dd class="mt-1 whitespace-pre-line text-red-950">{{ $requisicao->motivo_reprovacao }}</dd>
                            <dd class="mt-1.5 text-[13px] text-red-800/80">{{ $requisicao->reprovadoPor?->nome }}, {{ $dataHora($requisicao->reprovado_em) }}</dd>
                        </div>
                    @endif
                    @if ($requisicao->motivo_cancelamento)
                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <dt class="text-[13px] font-medium text-slate-600">Motivo do cancelamento</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ $requisicao->motivo_cancelamento }}</dd>
                            <dd class="mt-1.5 text-[13px] text-slate-500">{{ $requisicao->canceladoPor?->nome }}, {{ $dataHora($requisicao->cancelado_em) }}</dd>
                        </div>
                    @endif
                </dl>
            </article>

            <section class="quadro" aria-labelledby="itens-titulo">
                <h2 id="itens-titulo" class="quadro-titulo">Itens ({{ $requisicao->itens->count() }})</h2>
                {{-- Celular: um item por linha, com as quantidades embaixo da descrição. --}}
                <ol class="divide-y divide-slate-100 sm:hidden">
                    @foreach ($requisicao->itens as $item)
                        <li class="flex gap-3 px-5 py-3 text-sm">
                            <span class="w-5 shrink-0 text-right text-slate-400 tabular-nums">{{ $loop->iteration }}</span>
                            <div class="min-w-0">
                                <p>{{ $item->descricao }}</p>
                                <p class="mt-0.5 flex flex-wrap gap-x-4 text-[13px] text-slate-500">
                                    <span>Solicitada: <span class="font-medium text-slate-900 tabular-nums">{{ \App\Support\Quantidade::formatar($item->qtd_solicitada) }}</span> {{ $item->unidade }}</span>
                                    <span>Separada: <span class="font-medium text-slate-900 tabular-nums">{{ \App\Support\Quantidade::formatar($item->qtd_separada) }}</span></span>
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <div class="hidden overflow-x-auto sm:block">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th scope="col" class="w-12 rounded-none! text-right">#</th>
                                <th scope="col">Descrição</th>
                                <th scope="col" class="w-24">Unidade</th>
                                <th scope="col" class="w-32 text-right">Qtd. solicitada</th>
                                <th scope="col" class="w-32 rounded-none! text-right">Qtd. separada</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requisicao->itens as $item)
                                <tr>
                                    <td class="num text-slate-400">{{ $loop->iteration }}</td>
                                    <td>{{ $item->descricao }}</td>
                                    <td class="text-slate-600">{{ $item->unidade }}</td>
                                    <td class="num font-medium">{{ \App\Support\Quantidade::formatar($item->qtd_solicitada) }}</td>
                                    <td class="num text-slate-500">{{ \App\Support\Quantidade::formatar($item->qtd_separada) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="quadro" aria-labelledby="assinaturas-titulo">
                <h2 id="assinaturas-titulo" class="quadro-titulo">Assinaturas</h2>
                {{-- Linha do tempo: as etapas feitas, na ordem em que aconteceram, e depois as que faltam. --}}
                <ol class="p-5">
                    @foreach ($blocos as $bloco)
                        @php
                            $assinatura = $bloco['assinatura'];
                            $aguardando = ! $assinatura && $bloco['etapa'] === \App\Enums\EtapaAssinatura::APROVACAO_SETOR && $aguardandoAprovacao;
                        @endphp
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @unless ($loop->last)
                                <span @class(['absolute top-7 bottom-0 left-3 w-px -translate-x-1/2', 'bg-emerald-200' => $assinatura, 'bg-slate-200' => ! $assinatura]) aria-hidden="true"></span>
                            @endunless
                            <span @class([
                                'relative flex size-6 shrink-0 items-center justify-center rounded-full',
                                'bg-emerald-100 text-emerald-700' => $assinatura,
                                'bg-amber-100 text-amber-700' => $aguardando,
                                'bg-slate-100 text-slate-400' => ! $assinatura && ! $aguardando,
                            ]) aria-hidden="true">
                                @if ($assinatura)
                                    <x-phosphor-check-bold class="size-3.5" />
                                @elseif ($aguardando)
                                    <x-phosphor-clock-bold class="size-3.5" />
                                @else
                                    <span class="size-1.5 rounded-full bg-current"></span>
                                @endif
                            </span>
                            <div class="min-w-0 pt-0.5">
                                <p @class(['text-sm font-medium', 'text-slate-900' => $assinatura || $aguardando, 'text-slate-500' => ! $assinatura && ! $aguardando])>{{ $bloco['etapa']->rotulo() }}</p>
                                @if ($assinatura)
                                    <p class="mt-0.5 text-sm">{{ $assinatura->nome_assinante }}@if ($assinatura->cargo_assinante)<span class="text-slate-500">, {{ $assinatura->cargo_assinante }}</span>@endif</p>
                                    <p class="mt-0.5 flex flex-wrap gap-x-3 text-[13px] text-slate-500">
                                        <span class="tabular-nums">{{ $dataHora($assinatura->assinado_em) }}</span>
                                        <span class="font-mono text-slate-700" title="Código de verificação">{{ \App\Services\AssinaturaService::codigoCurto($assinatura->hash_documento) }}</span>
                                    </p>
                                @elseif ($aguardando)
                                    <p class="mt-0.5 text-sm text-amber-800">
                                        @if ($aguardandoAprovacaoDe->isEmpty())
                                            Nenhum aprovador disponível. Procure a TI.
                                        @else
                                            Aguardando {{ $aguardandoAprovacaoDe->pluck('nome')->join(', ', ' ou ') }}
                                        @endif
                                    </p>
                                    @if ($motivoSemAprovador)
                                        <p class="mt-0.5 text-[13px] text-slate-500">Pelos líderes do estoque: {{ mb_strtolower($motivoSemAprovador) }}.</p>
                                    @endif
                                @else
                                    <p class="text-[13px] text-slate-400">Pendente</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p @class([
                    'flex items-start gap-2 border-t border-slate-100 px-5 py-3 text-[13px]',
                    'text-emerald-700' => $verificacao->integro,
                    'font-semibold text-red-700' => ! $verificacao->integro,
                ])>
                    @if ($verificacao->integro)
                        <x-phosphor-seal-check class="mt-px size-4 shrink-0" aria-hidden="true" />
                        Assinaturas íntegras: o conteúdo confere com o que foi assinado em cada etapa.
                    @else
                        <x-phosphor-warning class="mt-px size-4 shrink-0" aria-hidden="true" />
                        Divergente: o conteúdo não confere com as assinaturas.
                    @endif
                </p>
            </section>

            <section class="quadro" aria-labelledby="historico-titulo">
                <h2 id="historico-titulo" class="quadro-titulo">Histórico</h2>
                <ol class="divide-y divide-slate-100 text-sm">
                    @foreach ($requisicao->eventos as $evento)
                        <li class="px-5 py-3">
                            <p class="flex items-baseline justify-between gap-3">
                                <span class="font-medium">{{ $evento->acao->rotulo() }}</span>
                                <span class="shrink-0 text-[13px] text-slate-500 tabular-nums">{{ $evento->created_at->format('d/m/Y H:i') }}</span>
                            </p>
                            <p class="mt-0.5 text-slate-600">
                                {{ $evento->usuario?->nome }}
                                @if (is_string($evento->dados['motivo'] ?? null))
                                    <span class="block text-slate-500">Motivo: {{ $evento->dados['motivo'] }}</span>
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </div>

    @push('modais')
        @can('aprovar', $requisicao)
            <x-modal nome="aprovar" :titulo="'Aprovar '.$requisicao->numero" :abrir="old('_acao') === 'aprovar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.aprovar', $requisicao) }}">
                    @csrf
                    <input type="hidden" name="_acao" value="aprovar">
                    <div class="space-y-4 px-5 py-4">
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
                    <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario">Assinar e aprovar</button>
                    </div>
                </form>
            </x-modal>

            <x-modal nome="reprovar" :titulo="'Reprovar '.$requisicao->numero" :abrir="old('_acao') === 'reprovar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.reprovar', $requisicao) }}">
                    @csrf
                    <input type="hidden" name="_acao" value="reprovar">
                    <div class="space-y-4 px-5 py-4">
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
                    <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
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
                    <div class="space-y-4 px-5 py-4">
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
                    <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-perigo">Cancelar requisição</button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endpush
</x-layouts.app>
