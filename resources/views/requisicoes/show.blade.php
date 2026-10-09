<x-layouts.app :titulo="$requisicao->numero" :secao="$voltar['secao']">
    @php
        $dataHora = fn ($data) => $data->format('d/m/Y').' às '.$data->format('H:i');
        // Caixas de assinatura: as feitas, na ordem em que aconteceram, e depois as que faltam.
        $blocos = $requisicao->assinaturas
            ->map(fn ($assinatura) => ['etapa' => $assinatura->etapa, 'assinatura' => $assinatura])
            ->concat(collect($pendentes)->map(fn ($etapa) => ['etapa' => $etapa, 'assinatura' => null]));
        $aguardandoAprovacao = $requisicao->status === \App\Enums\StatusRequisicao::AGUARDANDO_APROVACAO;
    @endphp

    <a href="{{ $voltar['url'] }}" data-voltar class="-ml-2 inline-flex h-8 items-center gap-1.5 rounded-full px-2 text-sm font-medium text-mid-gray transition-colors hover:bg-hairline hover:text-ink">
        <x-phosphor-arrow-left class="size-4" aria-hidden="true" />Voltar para {{ $voltar['rotulo'] }}
    </a>

    <div class="mt-3 mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 id="numero-requisicao" class="font-display text-2xl font-bold">{{ $requisicao->numero }}</h1>
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
            @can('decidirCompra', $requisicao)
                <button type="button" class="botao botao-perigo-contorno" x-data x-on:click="$dispatch('abrir-modal', 'reprovar-compra')">Reprovar compra</button>
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'aprovar-compra')">
                    <x-phosphor-check class="size-[18px]" aria-hidden="true" />Aprovar compra
                </button>
            @endcan
            @can('separar', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'separar')">
                    <x-phosphor-package class="size-[18px]" aria-hidden="true" />{{ $requisicao->tipo === \App\Enums\TipoRequisicao::TESTE ? 'Separar e entregar' : 'Separar' }}
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
                    <x-phosphor-package class="size-[18px]" aria-hidden="true" />Entregar
                </button>
            @endcan
            @can('devolver', $requisicao)
                <button type="button" class="botao botao-primario" x-data x-on:click="$dispatch('abrir-modal', 'devolver')">
                    <x-phosphor-arrow-counter-clockwise class="size-[18px]" aria-hidden="true" />Conferir devolução
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
        <div role="alert" class="mb-6 flex gap-3 rounded-lg border border-destrutivo/35 bg-paper p-4 text-sm text-ink">
            <x-phosphor-warning-octagon class="size-5 shrink-0 text-destrutivo" aria-hidden="true" />
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
                            @elseif ($requisicao->tipo === \App\Enums\TipoRequisicao::COMPRA_FUNCIONARIO)
                                <span class="text-mid-gray">Não se aplica (compra)</span>
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
                    @if (filled($requisicao->justificativa))
                        <div>
                            <dt class="legenda">Justificativa</dt>
                            <dd class="mt-1 whitespace-pre-line">{{ $requisicao->justificativa }}</dd>
                        </div>
                    @endif
                    @if ($requisicao->retirado_por_nome || $requisicao->devolvido_por_nome)
                        <div class="grid grid-cols-2 gap-x-6">
                            @if ($requisicao->retirado_por_nome)
                                <div>
                                    <dt class="legenda">Retirado por</dt>
                                    <dd class="mt-1 font-medium">{{ $requisicao->retirado_por_nome }}</dd>
                                </div>
                            @endif
                            @if ($requisicao->devolvido_por_nome)
                                <div>
                                    <dt class="legenda">Devolvido por</dt>
                                    <dd class="mt-1 font-medium">{{ $requisicao->devolvido_por_nome }}</dd>
                                </div>
                            @endif
                        </div>
                    @endif
                    @if ($requisicao->motivo_reprovacao)
                        <div class="rounded-lg border border-destrutivo/35 p-4">
                            <dt class="text-[13px] font-medium text-destrutivo">Motivo da reprovação</dt>
                            <dd class="mt-1 whitespace-pre-line text-ink">{{ $requisicao->motivo_reprovacao }}</dd>
                            <dd class="mt-1.5 text-[13px] text-mid-gray">{{ $requisicao->reprovadoPor?->nome }}, {{ $dataHora($requisicao->reprovado_em) }}</dd>
                        </div>
                    @endif
                    @if ($requisicao->compra_decidido_por_id !== null)
                        <div class="rounded-lg border p-4 {{ $requisicao->status === \App\Enums\StatusRequisicao::COMPRA_REPROVADA ? 'border-destrutivo/35' : 'border-hairline' }}">
                            <dt class="text-[13px] font-medium {{ $requisicao->status === \App\Enums\StatusRequisicao::COMPRA_REPROVADA ? 'text-destrutivo' : 'text-ink' }}">
                                {{ $requisicao->status === \App\Enums\StatusRequisicao::COMPRA_REPROVADA ? 'Compra reprovada' : 'Compra aprovada' }}
                            </dt>
                            @if (filled($requisicao->compra_observacao))
                                <dd class="mt-1 whitespace-pre-line text-ink">{{ $requisicao->compra_observacao }}</dd>
                            @endif
                            <dd class="mt-1.5 text-[13px] text-mid-gray">{{ $requisicao->compraDecididaPor?->nome }}, {{ $dataHora($requisicao->compra_decidido_em) }}</dd>
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
                                <p>@if ($item->codigo)<span class="mr-1.5 font-medium tabular-nums text-mid-gray">{{ $item->codigo }}</span>@endif{{ $item->descricao }}</p>
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
                                <th scope="col" class="w-28">Cód.</th>
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
                                    <td class="tabular-nums text-mid-gray">{{ $item->codigo ?? '—' }}</td>
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
                                <span @class(['absolute top-7 bottom-0 left-3 w-px -translate-x-1/2', 'bg-sucesso/40' => $assinatura, 'bg-hairline' => ! $assinatura]) aria-hidden="true"></span>
                            @endunless
                            <span @class([
                                'relative flex size-6 shrink-0 items-center justify-center rounded-full',
                                'bg-sucesso text-white' => $assinatura,
                                'border border-aviso bg-aviso-suave text-aviso' => $aguardando,
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
                                    <p class="mt-0.5 text-sm text-ink">
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
                    'text-ink-soft' => $verificacao->integro,
                    'font-semibold text-destrutivo' => ! $verificacao->integro,
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
                        <p class="text-sm text-ink-soft">@if ($requisicao->tipo === \App\Enums\TipoRequisicao::COMPRA_FUNCIONARIO)Depois de aprovada, a compra vai para a Erica decidir.@else Depois de aprovada, a requisição vai para o estoque separar.@endif</p>
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
            @php
                $juntaEntrega = $requisicao->tipo === \App\Enums\TipoRequisicao::TESTE;
            @endphp
            <x-modal nome="separar" :titulo="($juntaEntrega ? 'Separar e entregar ' : 'Separar ').$requisicao->numero" :abrir="in_array(old('_acao'), ['separar', 'separar-entregar'], true) && $errors->any()">
                <form method="POST" action="{{ $juntaEntrega ? route('requisicoes.separar-entregar', $requisicao) : route('requisicoes.separar', $requisicao) }}"
                      x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="{{ $juntaEntrega ? 'separar-entregar' : 'separar' }}">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Confira a quantidade de cada item. O que não achar, deixe em 0. A requisição segue com o que foi separado.@if ($juntaEntrega) Informe quem está retirando: a entrega já fica registrada junto.@endif</p>
                        <div class="space-y-3">
                            @foreach ($requisicao->itens as $item)
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-ink">@if ($item->codigo)<span class="mr-1.5 font-medium tabular-nums text-mid-gray">{{ $item->codigo }}</span>@endif{{ $item->descricao }}</p>
                                        <p class="text-[13px] text-mid-gray">Pedido: {{ $semZeros($item->qtd_solicitada) }} {{ $item->unidade }}</p>
                                    </div>
                                    <div class="w-28 shrink-0">
                                        <label for="separar-item-{{ $item->id }}" class="sr-only">Quantidade separada de {{ $item->descricao }}</label>
                                        <input id="separar-item-{{ $item->id }}" name="itens[{{ $item->id }}]" type="text" inputmode="decimal" @if ($loop->first) data-foco @endif
                                               value="{{ in_array(old('_acao'), ['separar', 'separar-entregar'], true) && old("itens.{$item->id}") !== null ? old("itens.{$item->id}") : $semZeros($item->qtd_solicitada) }}"
                                               class="campo text-right tabular-nums @error('itens.'.$item->id) campo-erro @enderror">
                                    </div>
                                </div>
                                @error('itens.'.$item->id)
                                    <p class="erro-campo -mt-2">{{ $message }}</p>
                                @enderror
                            @endforeach
                        </div>
                        @if ($juntaEntrega)
                            <x-campo-pessoa nome="retirado_por_nome" id="separar-nome" rotulo="Quem está retirando" />
                        @endif
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            @if ($juntaEntrega)
                                <span x-text="enviando ? 'Enviando…' : 'Separar e entregar'"></span>
                            @else
                                <span x-text="enviando ? 'Enviando…' : 'Assinar e separar'"></span>
                            @endif
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
                <form method="POST" action="{{ route('requisicoes.entregar', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="entregar">
                    <div class="space-y-4 px-5 py-4">
                        <x-campo-pessoa nome="retirado_por_nome" id="entregar-nome" rotulo="Quem está retirando" data-foco />
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Confirmar entrega'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

            @can('devolver', $requisicao)
            @php
                $semZerosDevolucao = fn ($valor) => rtrim(rtrim((string) $valor, '0'), '.') ?: '0';
                $antigoDevolucao = fn ($item, $campo, $padrao) => old('_acao') === 'devolver' && old("itens.{$item->id}.{$campo}") !== null
                    ? old("itens.{$item->id}.{$campo}")
                    : $padrao;
                $divergenteAntes = fn ($item) => old('_acao') === 'devolver'
                    && (((float) old("itens.{$item->id}.defeito", 0)) > 0 || ((float) old("itens.{$item->id}.nao_devolvida", 0)) > 0 || $errors->has('itens.'.$item->id.'.ok'));
            @endphp
            <x-modal nome="devolver" titulo="Conferir devolução" largura="max-w-4xl"
                     :subtitulo="$requisicao->numero.' · Solicitante: '.$requisicao->solicitante->nome.' · '.$requisicao->itens->count().($requisicao->itens->count() === 1 ? ' item' : ' itens')"
                     :abrir="old('_acao') === 'devolver' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.devolver', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="devolver">
                    <div class="grid gap-7 px-6 py-5 md:grid-cols-[1.3fr_1fr]">
                        <div>
                            <p class="etiqueta mb-3.5 border-b border-hairline pb-1.5">Conferência</p>
                            <div class="space-y-3">
                                @foreach ($requisicao->itens as $item)
                                    <div class="rounded-lg border border-hairline p-3.5" x-data="{ resultado: '{{ $divergenteAntes($item) ? 'divergente' : 'ok' }}' }">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium text-ink">@if ($item->codigo)<span class="mr-1.5 font-medium tabular-nums text-mid-gray">{{ $item->codigo }}</span>@endif{{ $item->descricao }}</p>
                                                <p class="text-[13px] text-mid-gray">Solicitada: {{ $semZerosDevolucao($item->qtd_solicitada) }} {{ $item->unidade }}</p>
                                            </div>
                                            <div class="w-40 shrink-0">
                                                <label for="devolver-{{ $item->id }}-resultado" class="sr-only">Resultado de {{ $item->descricao }}</label>
                                                <select id="devolver-{{ $item->id }}-resultado" x-model="resultado" @if ($loop->first) data-foco @endif class="campo h-9 py-0 text-[13px]">
                                                    <option value="ok">Voltou tudo OK</option>
                                                    <option value="divergente">Divergente</option>
                                                </select>
                                            </div>
                                        </div>

                                        {{-- OK: tudo voltou bom. Os valores seguem por campos escondidos. --}}
                                        <template x-if="resultado === 'ok'">
                                            <div>
                                                <input type="hidden" name="itens[{{ $item->id }}][ok]" value="{{ $semZerosDevolucao($item->qtd_solicitada) }}">
                                                <input type="hidden" name="itens[{{ $item->id }}][defeito]" value="0">
                                                <input type="hidden" name="itens[{{ $item->id }}][nao_devolvida]" value="0">
                                            </div>
                                        </template>

                                        <template x-if="resultado === 'divergente'">
                                            <div class="mt-3">
                                                <div class="grid grid-cols-3 gap-2">
                                                    <div>
                                                        <label for="devolver-{{ $item->id }}-ok" class="legenda">Boa</label>
                                                        <input id="devolver-{{ $item->id }}-ok" name="itens[{{ $item->id }}][ok]" type="text" inputmode="decimal"
                                                               value="{{ $antigoDevolucao($item, 'ok', '0') }}"
                                                               class="campo mt-1 text-right tabular-nums @error('itens.'.$item->id.'.ok') campo-erro @enderror">
                                                    </div>
                                                    <div>
                                                        <label for="devolver-{{ $item->id }}-defeito" class="legenda">Defeito</label>
                                                        <input id="devolver-{{ $item->id }}-defeito" name="itens[{{ $item->id }}][defeito]" type="text" inputmode="decimal"
                                                               value="{{ $antigoDevolucao($item, 'defeito', '0') }}"
                                                               class="campo mt-1 text-right tabular-nums">
                                                    </div>
                                                    <div>
                                                        <label for="devolver-{{ $item->id }}-nao-devolvida" class="legenda">Não voltou</label>
                                                        <input id="devolver-{{ $item->id }}-nao-devolvida" name="itens[{{ $item->id }}][nao_devolvida]" type="text" inputmode="decimal"
                                                               value="{{ $antigoDevolucao($item, 'nao_devolvida', '0') }}"
                                                               class="campo mt-1 text-right tabular-nums">
                                                    </div>
                                                </div>
                                                <p class="ajuda">Os três juntos têm que fechar com {{ $semZerosDevolucao($item->qtd_solicitada) }}.</p>
                                                @error('itens.'.$item->id.'.ok')
                                                    <p class="erro-campo">{{ $message }}</p>
                                                @enderror
                                                <div class="mt-3">
                                                    <label for="devolver-{{ $item->id }}-obs" class="legenda">Observação da divergência</label>
                                                    <textarea id="devolver-{{ $item->id }}-obs" name="itens[{{ $item->id }}][observacao]" rows="2" maxlength="1000"
                                                              class="campo mt-1">{{ $antigoDevolucao($item, 'observacao', '') }}</textarea>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                @endforeach

                                <x-campo-pessoa nome="devolvido_por_nome" id="devolver-nome" rotulo="Quem está devolvendo" />
                            </div>
                        </div>

                        <div>
                            <p class="etiqueta mb-3.5 border-b border-hairline pb-1.5">Sobre a requisição</p>
                            <dl class="text-[13px]">
                                <div class="flex justify-between gap-3 border-b border-hairline py-1.5"><dt class="text-mid-gray">Setor</dt><dd class="text-right">{{ $requisicao->setor->nome }}</dd></div>
                                <div class="flex justify-between gap-3 border-b border-hairline py-1.5"><dt class="text-mid-gray">Retirado por</dt><dd class="text-right">{{ $requisicao->retirado_por_nome ?: '—' }}</dd></div>
                                <div class="flex justify-between gap-3 border-b border-hairline py-1.5"><dt class="text-mid-gray">Entregue em</dt><dd class="text-right tabular-nums">{{ $requisicao->entregue_em?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                                <div class="flex justify-between gap-3 py-1.5"><dt class="text-mid-gray">Devolução prevista</dt><dd class="text-right tabular-nums">{{ $requisicao->data_prevista_devolucao?->format('d/m/Y') ?? '—' }}</dd></div>
                            </dl>

                            <p class="etiqueta mt-6 mb-3.5 border-b border-hairline pb-1.5">Finalidade</p>
                            <p class="text-[13px] whitespace-pre-line text-ink-soft">{{ $requisicao->finalidade }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-hairline bg-fundo px-6 py-4">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Cancelar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Conferir devolução'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('decidirCompra', $requisicao)
            <x-modal nome="aprovar-compra" :titulo="'Aprovar compra '.$requisicao->numero" :abrir="old('_acao') === 'aprovar-compra' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.decidir-compra', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="aprovar-compra">
                    <input type="hidden" name="decisao" value="aprovar">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Depois de aprovada, a compra está liberada e o status é atualizado para o solicitante.</p>
                        <div>
                            <label for="aprovar-compra-observacao" class="rotulo">Observação (opcional)</label>
                            <textarea id="aprovar-compra-observacao" name="observacao" rows="3" maxlength="1000"  data-foco
                                      class="campo mt-1.5 @error('observacao') campo-erro @enderror">{{ old('_acao') === 'aprovar-compra' && is_string(old('observacao')) ? old('observacao') : '' }}</textarea>
                            @error('observacao')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-primario" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Aprovar compra'"></span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan

        @can('decidirCompra', $requisicao)
            <x-modal nome="reprovar-compra" :titulo="'Reprovar compra '.$requisicao->numero" :abrir="old('_acao') === 'reprovar-compra' && $errors->any()">
                <form method="POST" action="{{ route('requisicoes.decidir-compra', $requisicao) }}" x-data="{ enviando: false }" x-on:submit="enviando = true">
                    @csrf
                    <input type="hidden" name="_acao" value="reprovar-compra">
                    <input type="hidden" name="decisao" value="reprovar">
                    <div class="space-y-4 px-5 py-4">
                        <p class="text-sm text-ink-soft">Informe o motivo. O solicitante vai ver a reprovação e o motivo.</p>
                        <div>
                            <label for="reprovar-compra-observacao" class="rotulo">Motivo da reprovação</label>
                            <textarea id="reprovar-compra-observacao" name="observacao" rows="3" maxlength="1000" required data-foco
                                      class="campo mt-1.5 @error('observacao') campo-erro @enderror">{{ old('_acao') === 'reprovar-compra' && is_string(old('observacao')) ? old('observacao') : '' }}</textarea>
                            @error('observacao')
                                <p class="erro-campo">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-surface-alt px-5 py-3">
                        <button type="button" class="botao botao-secundario" x-on:click="aberto = false">Fechar</button>
                        <button type="submit" class="botao botao-perigo" :disabled="enviando">
                            <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                            <span x-text="enviando ? 'Enviando…' : 'Reprovar compra'"></span>
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
