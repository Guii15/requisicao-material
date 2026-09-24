<x-layouts.app :titulo="$requisicao->numero" :secao="$voltar['secao']">
    @php
        $dataHora = fn ($data) => $data->format('d/m/Y').' às '.$data->format('H:i');
        // Caixas de assinatura: as feitas, na ordem em que aconteceram, e depois as que faltam.
        $blocos = $requisicao->assinaturas
            ->map(fn ($assinatura) => ['etapa' => $assinatura->etapa, 'assinatura' => $assinatura])
            ->concat(collect($pendentes)->map(fn ($etapa) => ['etapa' => $etapa, 'assinatura' => null]));
        $aguardandoAprovacao = $requisicao->status === \App\Enums\StatusRequisicao::AGUARDANDO_APROVACAO;
    @endphp

    <a href="{{ $voltar['url'] }}" data-voltar class="-ml-2 inline-flex h-8 items-center gap-1.5 rounded-lg px-2 text-sm font-medium text-mid-gray transition-colors hover:bg-hairline hover:text-ink">
        <x-phosphor-arrow-left class="size-4" aria-hidden="true" />Voltar para {{ $voltar['rotulo'] }}
    </a>

    <div class="mt-3 mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 id="numero-requisicao" class="font-mono text-2xl font-semibold tracking-tight">{{ $requisicao->numero }}</h1>
                <x-selo-tipo :tipo="$requisicao->tipo" />
            </div>
            <p class="mt-1.5 flex flex-wrap items-center gap-x-2 text-sm text-mid-gray">
                <x-selo-status :status="$requisicao->status" class="font-medium text-ink" />
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
            @can('separar', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'separar')">
                    <x-phosphor-package class="size-[18px]" aria-hidden="true" />Separar
                </button>
            @endcan
            @can('reprovarEstoque', $requisicao)
                <button type="button" class="botao botao-perigo-contorno" x-data x-on:click="$dispatch('abrir-modal', 'reprovar-estoque')">Reprovar no estoque</button>
            @endcan
            @can('liberar', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'liberar')">
                    <x-phosphor-lock-open class="size-[18px]" aria-hidden="true" />Liberar
                </button>
            @endcan
            @can('entregar', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'entregar')">
                    <x-phosphor-hand-arrow-up class="size-[18px]" aria-hidden="true" />Entregar
                </button>
            @endcan
            @can('confirmarRecebimento', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'confirmar-recebimento')">
                    <x-phosphor-check class="size-[18px]" aria-hidden="true" />Confirmar recebimento
                </button>
            @endcan
            @can('devolver', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'devolver')">
                    <x-phosphor-arrow-u-down-left class="size-[18px]" aria-hidden="true" />Conferir devolução
                </button>
            @endcan
            @can('darBaixa', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'baixa')">
                    <x-phosphor-receipt class="size-[18px]" aria-hidden="true" />Dar baixa
                </button>
            @endcan
        </div>
    </div>

    @unless ($verificacao->integro)
        <div role="alert" class="mb-6 flex gap-3 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-950">
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
                                <span class="text-mid-gray">Não volta (uso e consumo)</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <dl class="space-y-5 border-t border-hairline p-5">
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
                        <div class="rounded-lg border border-hairline bg-surface-alt p-4">
                            <dt class="text-[13px] font-medium text-mid-gray">Motivo do cancelamento</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ $requisicao->motivo_cancelamento }}</dd>
                            <dd class="mt-1.5 text-[13px] text-mid-gray">{{ $requisicao->canceladoPor?->nome }}, {{ $dataHora($requisicao->cancelado_em) }}</dd>
                        </div>
                    @endif
                </dl>
            </article>

            <section class="quadro" aria-labelledby="itens-titulo">
                <h2 id="itens-titulo" class="quadro-titulo">Itens ({{ $requisicao->itens->count() }})</h2>
                {{-- Celular: um item por linha, com as quantidades embaixo da descrição. --}}
                <ol class="divide-y divide-hairline sm:hidden">
                    @foreach ($requisicao->itens as $item)
                        <li class="flex gap-3 px-5 py-3 text-sm">
                            <span class="w-5 shrink-0 text-right text-mid-gray tabular-nums">{{ $loop->iteration }}</span>
                            <div class="min-w-0">
                                <p>{{ $item->descricao }}</p>
                                <p class="mt-0.5 flex flex-wrap gap-x-4 text-[13px] text-mid-gray">
                                    <span>Solicitada: <span class="font-medium text-ink tabular-nums">{{ \App\Support\Quantidade::formatar($item->qtd_solicitada) }}</span> {{ $item->unidade }}</span>
                                    <span>Separada: <span class="font-medium text-ink tabular-nums">{{ \App\Support\Quantidade::formatar($item->qtd_separada) }}</span></span>
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
                                    <td class="num text-mid-gray">{{ $loop->iteration }}</td>
                                    <td>{{ $item->descricao }}</td>
                                    <td class="text-mid-gray">{{ $item->unidade }}</td>
                                    <td class="num font-medium">{{ \App\Support\Quantidade::formatar($item->qtd_solicitada) }}</td>
                                    <td class="num text-mid-gray">{{ \App\Support\Quantidade::formatar($item->qtd_separada) }}</td>
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
                                <span @class(['absolute top-7 bottom-0 left-3 w-px -translate-x-1/2', 'bg-emerald-200' => $assinatura, 'bg-hairline' => ! $assinatura]) aria-hidden="true"></span>
                            @endunless
                            <span @class([
                                'relative flex size-6 shrink-0 items-center justify-center rounded-full',
                                'bg-emerald-100 text-emerald-700' => $assinatura,
                                'bg-amber-100 text-amber-700' => $aguardando,
                                'bg-surface-alt text-mid-gray' => ! $assinatura && ! $aguardando,
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
                                <p @class(['text-sm font-medium', 'text-ink' => $assinatura || $aguardando, 'text-mid-gray' => ! $assinatura && ! $aguardando])>{{ $bloco['etapa']->rotulo() }}</p>
                                @if ($assinatura)
                                    <p class="mt-0.5 text-sm">{{ $assinatura->nome_assinante }}@if ($assinatura->cargo_assinante)<span class="text-mid-gray">, {{ $assinatura->cargo_assinante }}</span>@endif</p>
                                    <p class="mt-0.5 flex flex-wrap gap-x-3 text-[13px] text-mid-gray">
                                        <span class="tabular-nums">{{ $dataHora($assinatura->assinado_em) }}</span>
                                        <span class="font-mono text-ink-soft" title="Código de verificação">{{ \App\Services\AssinaturaService::codigoCurto($assinatura->hash_documento) }}</span>
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
                                        <p class="mt-0.5 text-[13px] text-mid-gray">Pelos líderes do estoque: {{ mb_strtolower($motivoSemAprovador) }}.</p>
                                    @endif
                                @else
                                    <p class="text-[13px] text-mid-gray">Pendente</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
                <p @class([
                    'flex items-start gap-2 border-t border-hairline px-5 py-3 text-[13px]',
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
                <ol class="divide-y divide-hairline text-sm">
                    @foreach ($requisicao->eventos as $evento)
                        <li class="px-5 py-3">
                            <p class="flex items-baseline justify-between gap-3">
                                <span class="font-medium">{{ $evento->acao->rotulo() }}</span>
                                <span class="shrink-0 text-[13px] text-mid-gray tabular-nums">{{ $evento->created_at->format('d/m/Y H:i') }}</span>
                            </p>
                            <p class="mt-0.5 text-mid-gray">
                                {{ $evento->usuario?->nome }}
                                @if (is_string($evento->dados['motivo'] ?? null))
                                    <span class="block text-mid-gray">Motivo: {{ $evento->dados['motivo'] }}</span>
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
                <form method="POST" action="{{ route('requisicoes.aprovar', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="aprovar">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Depois de aprovada, a requisição vai para o estoque separar.</p>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Assinar e aprovar'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>

            <x-modal nome="reprovar" :titulo="'Reprovar '.$requisicao->numero" :abrir="old('_acao') === 'reprovar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.reprovar', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
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
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-perigo" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Assinar e reprovar'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('separar', $requisicao)
            @php
                // "1.000" fica feio pra digitar de novo: mostra sem zero à direita, mas manda o valor certo se a pessoa não mexer.
                $semZeros = fn ($valor) => rtrim(rtrim((string) $valor, '0'), '.') ?: '0';
            @endphp
            <x-modal nome="separar" :titulo="'Separar '.$requisicao->numero" :abrir="old('_acao') === 'separar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.separar', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="separar">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Confira a quantidade de cada item. O que não achar, deixe em 0 — a requisição segue com o que foi separado.</p>
                        <div class="space-y-3">
                            @foreach ($requisicao->itens as $item)
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-ink">{{ $item->descricao }}</p>
                                        <p class="text-[13px] text-mid-gray">Pedido: {{ $semZeros($item->qtd_solicitada) }} {{ $item->unidade }}</p>
                                    </div>
                                    <div class="w-28 shrink-0">
                                        <label for="separar-item-{{ $item->id }}" class="sr-only">Quantidade separada de {{ $item->descricao }}</label>
                                        <input id="separar-item-{{ $item->id }}" name="itens[{{ $item->id }}]" type="text" inputmode="decimal" @if ($loop->first) data-foco @endif
                                               value="{{ old('_acao') === 'separar' && old("itens.{$item->id}") !== null ? old("itens.{$item->id}") : $semZeros($item->qtd_solicitada) }}"
                                               class="campo text-right tabular-nums @error('itens.'.$item->id) campo-erro @enderror">
                                    </div>
                                </div>
                                @error('itens.'.$item->id)
                                    <p class="erro-campo -mt-2">{{ $message }}</p>
                                @enderror
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Assinar e separar'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('liberar', $requisicao)
            <x-modal nome="liberar" :titulo="'Liberar '.$requisicao->numero" :abrir="old('_acao') === 'liberar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.liberar', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="liberar">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Depois de liberada, a requisição vai pra fila de entrega.</p>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Assinar e liberar'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>

            <x-modal nome="reprovar-estoque" :titulo="'Reprovar no estoque '.$requisicao->numero" :abrir="old('_acao') === 'reprovar-estoque' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.reprovar-estoque', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="reprovar-estoque">
                    <div class="space-y-4 px-5 py-4">
                        <div>
                            <label for="reprovar-estoque-motivo" class="rotulo">Motivo da reprovação</label>
                            <p class="ajuda">O solicitante vai ver este texto.</p>
                            <textarea id="reprovar-estoque-motivo" name="motivo" rows="3" maxlength="1000" required data-foco
                                      class="campo mt-1.5 @error('motivo') campo-erro @enderror">{{ old('_acao') === 'reprovar-estoque' && is_string(old('motivo')) ? old('motivo') : '' }}</textarea>
                            @error('motivo')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-perigo" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Assinar e reprovar'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('entregar', $requisicao)
            <x-modal nome="entregar" :titulo="'Entregar '.$requisicao->numero" :abrir="old('_acao') === 'entregar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.entregar', $requisicao) }}" x-data="assinaturaDesenho()" x-on:submit="aoEnviar()">
                    @csrf
                    <input type="hidden" name="_acao" value="entregar">
                    <input type="hidden" name="assinatura" :value="dataUrl">
                    <div class="space-y-4 px-5 py-4">
                        <div>
                            <label for="entregar-nome" class="rotulo">Quem está retirando</label>
                            <input id="entregar-nome" name="retirado_por_nome" type="text" required data-foco
                                   value="{{ old('retirado_por_nome') }}"
                                   class="campo mt-1.5 @error('retirado_por_nome') campo-erro @enderror">
                            @error('retirado_por_nome')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="rotulo">Assinatura de quem retira</label>
                            <p class="ajuda">Peça pra pessoa desenhar aqui na tela, com o dedo ou o mouse.</p>
                            <div class="mt-1.5 overflow-hidden rounded-lg border border-hairline bg-fundo">
                                <canvas x-ref="tela" class="h-40 w-full touch-none"
                                        x-on:mousedown="comecar" x-on:mousemove="desenhar" x-on:mouseup="parar" x-on:mouseleave="parar"
                                        x-on:touchstart="comecar" x-on:touchmove="desenhar" x-on:touchend="parar"></canvas>
                            </div>
                            <button type="button" x-show="!vazio" x-cloak x-on:click="limpar" class="mt-1.5 text-sm font-medium text-mid-gray hover:text-ink">Limpar e assinar de novo</button>
                            @error('assinatura')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando || vazio">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : (vazio ? 'Falta a assinatura' : 'Assinar e entregar')"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('confirmarRecebimento', $requisicao)
            <x-modal nome="confirmar-recebimento" :titulo="'Confirmar recebimento '.$requisicao->numero" :abrir="old('_acao') === 'confirmar-recebimento' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.confirmar-recebimento', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="confirmar-recebimento">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Confirme que o material chegou às suas mãos. Isso só fica registrado — a situação continua a mesma.</p>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Assinar e confirmar'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('devolver', $requisicao)
            @php
                $semZerosDevolucao ??= fn ($valor) => rtrim(rtrim((string) $valor, '0'), '.') ?: '0';
                $antigoDevolucao = fn ($item, $campo, $padrao) => old('_acao') === 'devolver' && old("itens.{$item->id}.{$campo}") !== null
                    ? old("itens.{$item->id}.{$campo}")
                    : $padrao;
            @endphp
            <x-modal nome="devolver" :titulo="'Conferir devolução '.$requisicao->numero" :abrir="old('_acao') === 'devolver' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.devolver', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="devolver">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Pra cada item, confira quanto voltou bom, quanto veio com defeito e quanto não voltou. Os três juntos têm que fechar com a quantidade solicitada.</p>
                        <div class="space-y-4">
                            @foreach ($requisicao->itens as $item)
                                <div class="rounded-lg border border-hairline p-3">
                                    <p class="text-sm font-medium text-ink">{{ $item->descricao }}</p>
                                    <p class="text-[13px] text-mid-gray">Solicitada: {{ $semZerosDevolucao($item->qtd_solicitada) }} {{ $item->unidade }}</p>
                                    <div class="mt-2 grid grid-cols-3 gap-2">
                                        <div>
                                            <label for="devolver-{{ $item->id }}-ok" class="text-[13px] text-mid-gray">Boa</label>
                                            <input id="devolver-{{ $item->id }}-ok" name="itens[{{ $item->id }}][ok]" type="text" inputmode="decimal" @if ($loop->first) data-foco @endif
                                                   value="{{ $antigoDevolucao($item, 'ok', $semZerosDevolucao($item->qtd_solicitada)) }}"
                                                   class="campo mt-1 text-right tabular-nums @error('itens.'.$item->id.'.ok') campo-erro @enderror">
                                        </div>
                                        <div>
                                            <label for="devolver-{{ $item->id }}-defeito" class="text-[13px] text-mid-gray">Defeito</label>
                                            <input id="devolver-{{ $item->id }}-defeito" name="itens[{{ $item->id }}][defeito]" type="text" inputmode="decimal"
                                                   value="{{ $antigoDevolucao($item, 'defeito', '0') }}"
                                                   class="campo mt-1 text-right tabular-nums @error('itens.'.$item->id.'.defeito') campo-erro @enderror">
                                        </div>
                                        <div>
                                            <label for="devolver-{{ $item->id }}-nao-devolvida" class="text-[13px] text-mid-gray">Não voltou</label>
                                            <input id="devolver-{{ $item->id }}-nao-devolvida" name="itens[{{ $item->id }}][nao_devolvida]" type="text" inputmode="decimal"
                                                   value="{{ $antigoDevolucao($item, 'nao_devolvida', '0') }}"
                                                   class="campo mt-1 text-right tabular-nums @error('itens.'.$item->id.'.nao_devolvida') campo-erro @enderror">
                                        </div>
                                    </div>
                                    @error('itens.'.$item->id.'.ok')
                                        <p class="erro-campo">{{ $message }}</p>
                                    @enderror
                                    <div class="mt-2">
                                        <label for="devolver-{{ $item->id }}-obs" class="text-[13px] text-mid-gray">Observação (opcional)</label>
                                        <input id="devolver-{{ $item->id }}-obs" name="itens[{{ $item->id }}][observacao]" type="text" maxlength="1000"
                                               value="{{ $antigoDevolucao($item, 'observacao', '') }}"
                                               class="campo mt-1">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Assinar e conferir'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('darBaixa', $requisicao)
            <x-modal nome="baixa" :titulo="'Dar baixa '.$requisicao->numero" :abrir="old('_acao') === 'baixa' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.dar-baixa', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="baixa">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Depois da baixa, a requisição está encerrada.</p>
                        <div>
                            <label for="baixa-documento" class="rotulo">Documento de baixa no WinThor</label>
                            <input id="baixa-documento" name="documento" type="text" required data-foco maxlength="40"
                                   value="{{ old('documento') }}"
                                   class="campo mt-1.5 @error('documento') campo-erro @enderror">
                            @error('documento')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="baixa-observacao" class="rotulo">Observação (opcional)</label>
                            <textarea id="baixa-observacao" name="observacao" rows="2" maxlength="1000"
                                      class="campo mt-1.5">{{ old('observacao') }}</textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Assinar e dar baixa'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('cancelar', $requisicao)
            <x-modal nome="cancelar" :titulo="'Cancelar '.$requisicao->numero" :abrir="old('_acao') === 'cancelar' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.cancelar', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="cancelar">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">A requisição é encerrada e não pode ser reaberta. Ela continua no histórico.</p>
                        <div>
                            <label for="cancelar-motivo" class="rotulo">Motivo do cancelamento</label>
                            <textarea id="cancelar-motivo" name="motivo" rows="3" maxlength="1000" required data-foco
                                      class="campo mt-1.5 @error('motivo') campo-erro @enderror">{{ old('_acao') === 'cancelar' && is_string(old('motivo')) ? old('motivo') : '' }}</textarea>
                            @error('motivo')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-perigo" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Cancelar requisição'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endpush
</x-layouts.app>
