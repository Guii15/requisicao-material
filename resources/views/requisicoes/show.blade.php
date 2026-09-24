<x-layouts.app :titulo="$requisicao->numero">
    @php
        $assinadas = $requisicao->assinaturas->pluck('etapa');
        $pendentes = $requisicao->status->isFinal()
            ? []
            : array_filter(\App\Enums\EtapaAssinatura::fluxo($requisicao->tipo), fn ($etapa) => ! $assinadas->contains($etapa));
        $dataHora = fn ($data) => $data->format('d/m/Y').' às '.$data->format('H:i');
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="font-mono text-xl font-semibold">{{ $requisicao->numero }}</h1>
                <x-selo-tipo :tipo="$requisicao->tipo" />
                <x-selo-status :status="$requisicao->status" />
            </div>
            <p class="mt-1 text-sm text-slate-600">
                Aberta por {{ $requisicao->solicitante->nome }} ({{ $requisicao->setor->nome }}) em {{ $dataHora($requisicao->created_at) }}.
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('cancelar', $requisicao)
                <button type="button" class="botao botao-secundario" x-data x-on:click="$dispatch('abrir-modal', 'cancelar')">
                    Cancelar requisição
                </button>
            @endcan
            @can('reprovar', $requisicao)
                <button type="button" class="botao botao-secundario text-red-800" x-data x-on:click="$dispatch('abrir-modal', 'reprovar')">
                    <x-phosphor-x-bold class="size-4" aria-hidden="true" />
                    Reprovar
                </button>
            @endcan
            @can('aprovar', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'aprovar')">
                    <x-phosphor-check-bold class="size-4" aria-hidden="true" />
                    Aprovar
                </button>
            @endcan
        </div>
    </div>

    @unless ($verificacao->integro)
        <div role="alert" class="mb-4 rounded-sm border border-red-400 bg-red-50 px-4 py-3 text-sm text-red-900">
            <p class="flex items-center gap-2 font-semibold">
                <x-phosphor-seal-warning-fill class="size-5" aria-hidden="true" />
                Documento divergente da assinatura
            </p>
            <ul class="mt-1.5 list-disc space-y-0.5 pl-7">
                @foreach ($verificacao->problemas as $problema)
                    <li>{{ $problema }}</li>
                @endforeach
            </ul>
        </div>
    @endunless

    @if ($motivoSemAprovador)
        <div class="mb-4 flex items-start gap-2 rounded-sm border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950">
            <x-phosphor-info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <span>Aprovação pelos líderes do estoque: {{ $motivoSemAprovador }}.</span>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
        <div class="space-y-4">
            <section class="painel" aria-labelledby="dados-titulo">
                <h2 id="dados-titulo" class="border-b border-slate-200 px-4 py-3 font-semibold">Dados da requisição</h2>
                <dl class="grid gap-x-6 gap-y-3 px-4 py-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-slate-600">Solicitante</dt>
                        <dd class="mt-0.5 font-medium">{{ $requisicao->solicitante->nome }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-600">Setor</dt>
                        <dd class="mt-0.5 font-medium">{{ $requisicao->setor->nome }}</dd>
                    </div>
                    @if ($requisicao->tipo->exigeDevolucao())
                        <div>
                            <dt class="text-xs text-slate-600">Devolução prevista</dt>
                            <dd class="mt-0.5 font-mono font-medium">{{ $requisicao->data_prevista_devolucao?->format('d/m/Y') }}</dd>
                        </div>
                    @endif
                    <div class="sm:col-span-3">
                        <dt class="text-xs text-slate-600">Finalidade</dt>
                        <dd class="mt-0.5 whitespace-pre-line">{{ $requisicao->finalidade }}</dd>
                    </div>
                    <div class="sm:col-span-3">
                        <dt class="text-xs text-slate-600">Justificativa</dt>
                        <dd class="mt-0.5 whitespace-pre-line">{{ $requisicao->justificativa }}</dd>
                    </div>
                    @if ($requisicao->motivo_reprovacao)
                        <div class="sm:col-span-3">
                            <dt class="text-xs text-slate-600">Motivo da reprovação</dt>
                            <dd class="mt-0.5 whitespace-pre-line">{{ $requisicao->motivo_reprovacao }}</dd>
                            <dd class="mt-0.5 text-xs text-slate-600">{{ $requisicao->reprovadoPor?->nome }}, {{ $dataHora($requisicao->reprovado_em) }}</dd>
                        </div>
                    @endif
                    @if ($requisicao->motivo_cancelamento)
                        <div class="sm:col-span-3">
                            <dt class="text-xs text-slate-600">Motivo do cancelamento</dt>
                            <dd class="mt-0.5 whitespace-pre-line">{{ $requisicao->motivo_cancelamento }}</dd>
                            <dd class="mt-0.5 text-xs text-slate-600">{{ $requisicao->canceladoPor?->nome }}, {{ $dataHora($requisicao->cancelado_em) }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="painel" aria-labelledby="itens-titulo">
                <h2 id="itens-titulo" class="border-b border-slate-200 px-4 py-3 font-semibold">Itens</h2>
                <div class="overflow-x-auto">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th scope="col" class="w-10 text-right">#</th>
                                <th scope="col">Descrição</th>
                                <th scope="col">Unidade</th>
                                <th scope="col" class="text-right">Solicitada</th>
                                <th scope="col" class="text-right">Separada</th>
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
        </div>

        <div class="space-y-4">
            <section class="painel" aria-labelledby="assinaturas-titulo">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                    <h2 id="assinaturas-titulo" class="font-semibold">Assinaturas</h2>
                    @if ($verificacao->integro)
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-800">
                            <x-phosphor-shield-check-fill class="size-4" aria-hidden="true" />
                            Assinaturas íntegras
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-red-800">
                            <x-phosphor-seal-warning-fill class="size-4" aria-hidden="true" />
                            Divergente
                        </span>
                    @endif
                </div>
                <ol class="divide-y divide-slate-200">
                    @foreach ($requisicao->assinaturas as $assinatura)
                        <li class="px-4 py-2.5">
                            <p class="text-xs text-slate-600">{{ $assinatura->etapa->rotulo() }}</p>
                            <p class="text-sm font-medium">
                                {{ $assinatura->nome_assinante }}
                                @if ($assinatura->cargo_assinante)
                                    <span class="font-normal text-slate-600">· {{ $assinatura->cargo_assinante }}</span>
                                @endif
                            </p>
                            <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-slate-600">
                                <span class="tabular-nums">{{ $dataHora($assinatura->assinado_em) }}</span>
                                <span class="font-mono text-slate-800" title="Código de verificação">{{ \App\Services\AssinaturaService::codigoCurto($assinatura->hash_documento) }}</span>
                            </p>
                        </li>
                    @endforeach
                    @foreach ($pendentes as $etapa)
                        <li class="flex items-baseline justify-between gap-3 px-4 py-2 text-xs text-slate-500">
                            <span>{{ $etapa->rotulo() }}</span>
                            <span>Pendente</span>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section class="painel" aria-labelledby="historico-titulo">
                <h2 id="historico-titulo" class="border-b border-slate-200 px-4 py-3 font-semibold">Histórico</h2>
                <ol class="space-y-3 px-4 py-3">
                    @foreach ($requisicao->eventos as $evento)
                        <li class="grid grid-cols-[7.5rem_minmax(0,1fr)] gap-2 text-sm">
                            <span class="pt-px text-xs text-slate-600 tabular-nums">{{ $evento->created_at->format('d/m/Y H:i') }}</span>
                            <div>
                                <p class="font-medium">{{ $evento->acao->rotulo() }}</p>
                                @if ($evento->usuario)
                                    <p class="text-xs text-slate-600">{{ $evento->usuario->nome }}</p>
                                @endif
                                @if (is_string($evento->dados['motivo'] ?? null))
                                    <p class="mt-0.5 text-xs text-slate-700">Motivo: {{ $evento->dados['motivo'] }}</p>
                                @endif
                            </div>
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
                    <div class="space-y-3 px-5 py-4">
                        <p class="text-sm text-slate-700">Depois de aprovada, a requisição vai para o estoque separar. Confirme com a sua senha para assinar a aprovação.</p>
                        <div>
                            <label for="aprovar-senha" class="rotulo">Sua senha</label>
                            <input id="aprovar-senha" name="senha" type="password" autocomplete="new-password" required data-foco
                                   class="campo mt-1.5 @error('senha') campo-erro @enderror">
                            @error('senha')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Voltar</button>
                        <button type="submit" class="botao botao-primario">
                            <x-phosphor-signature class="size-4" aria-hidden="true" />
                            Assinar e aprovar
                        </button>
                    </div>
                </form>
            </x-modal>

            <x-modal nome="reprovar" :titulo="'Reprovar '.$requisicao->numero" :abrir="old('_acao') === 'reprovar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.reprovar', $requisicao) }}">
                    @csrf
                    <input type="hidden" name="_acao" value="reprovar">
                    <div class="space-y-3 px-5 py-4">
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
                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Voltar</button>
                        <button type="submit" class="botao botao-perigo">
                            <x-phosphor-signature class="size-4" aria-hidden="true" />
                            Assinar e reprovar
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('cancelar', $requisicao)
            <x-modal nome="cancelar" :titulo="'Cancelar '.$requisicao->numero" :abrir="old('_acao') === 'cancelar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.cancelar', $requisicao) }}">
                    @csrf
                    <input type="hidden" name="_acao" value="cancelar">
                    <div class="space-y-3 px-5 py-4">
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
                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Voltar</button>
                        <button type="submit" class="botao botao-perigo">Cancelar requisição</button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endpush
</x-layouts.app>
