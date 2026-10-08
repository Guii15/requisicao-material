<x-layouts.app titulo="Nova requisição">
    @php
        $texto = fn (mixed $valor, string $padrao = '') => is_string($valor) ? $valor : $padrao;
        $itensAnteriores = collect(old('itens', []))
            ->filter(fn (mixed $item) => is_array($item))
            ->values()
            ->map(fn (array $item) => [
                'descricao' => $texto($item['descricao'] ?? null),
                'quantidade' => $texto($item['quantidade'] ?? null),
                'unidade' => $texto($item['unidade'] ?? null, 'UN'),
            ])
            ->all();
        $errosDosItens = collect($errors->getMessages())
            ->filter(fn (array $mensagens, string $campo) => str_starts_with($campo, 'itens.'))
            ->map(fn (array $mensagens) => $mensagens[0])
            ->all();
        $tipoAnterior = in_array(old('tipo'), ['TESTE', 'USO_CONSUMO'], true) ? old('tipo') : null;
    @endphp

    <div class="mb-6">
        <h1 class="titulo-pagina">Nova requisição</h1>
        <p class="mt-1 text-sm text-mid-gray">Peça material ao estoque. Depois de enviar, a requisição vai para a aprovação do seu setor.</p>
    </div>

    <form method="POST" action="{{ route('requisicoes.store') }}"
          class="grid max-w-[68rem] gap-10 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
          x-data="novaRequisicao(@js([
              'tipo' => $tipoAnterior,
              'itens' => $itensAnteriores,
              'erros' => (object) $errosDosItens,
          ]))"
          x-on:submit="enviando = true">
        @csrf

        <div class="space-y-6">
            <fieldset>
                <legend class="titulo-secao float-left w-full">Tipo de retirada</legend>
                <div class="clear-both grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer gap-3.5 rounded-2xl border bg-paper p-4 transition-colors focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ink"
                           :class="tipo === 'TESTE' ? 'border-ink ring-1 ring-ink' : 'border-hairline hover:border-hairline-campo'">
                        <input type="radio" name="tipo" value="TESTE" x-model="tipo" required
                               class="mt-1 size-4 border-hairline-campo text-ink focus:ring-0 focus:ring-offset-0">
                        <span>
                            <span class="block font-semibold tracking-tight text-ink">Teste</span>
                            <span class="mt-1 block text-sm text-mid-gray">O material sai para teste e volta ao estoque em até {{ $prazoDias }} dias úteis.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer gap-3.5 rounded-2xl border bg-paper p-4 transition-colors focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ink"
                           :class="tipo === 'USO_CONSUMO' ? 'border-ink ring-1 ring-ink' : 'border-hairline hover:border-hairline-campo'">
                        <input type="radio" name="tipo" value="USO_CONSUMO" x-model="tipo" required
                               class="mt-1 size-4 border-hairline-campo text-ink focus:ring-0 focus:ring-offset-0">
                        <span>
                            <span class="block font-semibold tracking-tight text-ink">Uso e consumo</span>
                            <span class="mt-1 block text-sm text-mid-gray">O material é consumido e não volta. Passa pela liberação do líder do estoque e pela baixa no WinThor.</span>
                        </span>
                    </label>
                </div>
                @error('tipo')
                    <p class="erro-campo pt-2">{{ $message }}</p>
                @enderror
            </fieldset>

            <section aria-labelledby="itens-titulo">
                <h2 id="itens-titulo" class="titulo-secao flex items-baseline justify-between">
                    <span>Itens</span>
                    <span class="text-sm font-normal text-mid-gray" x-text="itens.length === 1 ? '1 item' : itens.length + ' itens'"></span>
                </h2>

                {{-- No computador é uma grade; no celular cada item vira um bloco com os rótulos visíveis. --}}
                <div class="hidden grid-cols-[1.5rem_minmax(0,1fr)_7rem_6rem_2.25rem] gap-3 pb-1 sm:grid">
                    <span class="legenda text-right">#</span>
                    <span class="legenda">Descrição do produto</span>
                    <span class="legenda text-right">Quantidade</span>
                    <span class="legenda">Unidade</span>
                    <span class="sr-only">Remover</span>
                </div>

                <div class="divide-y divide-hairline">
                    <template x-for="(item, indice) in itens" :key="item.chave">
                        <div class="grid grid-cols-2 gap-x-3 gap-y-2 py-3 sm:grid-cols-[1.5rem_minmax(0,1fr)_7rem_6rem_2.25rem] sm:items-start"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0">
                            <div class="col-span-2 flex items-center justify-between sm:col-span-1 sm:block sm:pt-2.5 sm:text-right">
                                <span class="text-sm font-medium text-ink-soft sm:font-normal sm:text-mid-gray sm:tabular-nums">
                                    <span class="sm:hidden">Item </span><span x-text="indice + 1"></span>
                                </span>
                                <button type="button"
                                        class="cursor-pointer text-sm font-medium text-destrutivo hover:underline disabled:cursor-not-allowed disabled:text-mid-gray disabled:no-underline sm:hidden"
                                        x-on:click="remover(indice)" :disabled="itens.length === 1"
                                        :aria-label="`Remover item ${indice + 1}`">
                                    Remover
                                </button>
                            </div>

                            <div class="col-span-2 sm:col-span-1">
                                <label :for="`item-${item.chave}-descricao`" class="text-sm text-mid-gray sm:sr-only">Descrição do produto</label>
                                <input type="text" data-descricao maxlength="255" required
                                       class="campo mt-1 sm:mt-0" :class="erroDoItem(indice, 'descricao') && 'campo-erro'"
                                       :id="`item-${item.chave}-descricao`"
                                       :name="`itens[${indice}][descricao]`" x-model="item.descricao"
                                       placeholder="Ex.: SSD NVMe 1TB Kingston NV2">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'descricao')" x-text="erroDoItem(indice, 'descricao')"></p>
                            </div>

                            <div>
                                <label :for="`item-${item.chave}-quantidade`" class="text-sm text-mid-gray sm:sr-only">Quantidade</label>
                                <input type="text" inputmode="decimal" required
                                       class="campo mt-1 text-right tabular-nums sm:mt-0" :class="erroDoItem(indice, 'quantidade') && 'campo-erro'"
                                       :id="`item-${item.chave}-quantidade`"
                                       :name="`itens[${indice}][quantidade]`" x-model="item.quantidade">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'quantidade')" x-text="erroDoItem(indice, 'quantidade')"></p>
                            </div>

                            <div>
                                <label :for="`item-${item.chave}-unidade`" class="text-sm text-mid-gray sm:sr-only">Unidade</label>
                                <input type="text" list="unidades" maxlength="20" required
                                       class="campo mt-1 uppercase sm:mt-0" :class="erroDoItem(indice, 'unidade') && 'campo-erro'"
                                       :id="`item-${item.chave}-unidade`"
                                       :name="`itens[${indice}][unidade]`" x-model="item.unidade">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'unidade')" x-text="erroDoItem(indice, 'unidade')"></p>
                            </div>

                            <div class="hidden sm:block">
                                <button type="button"
                                        class="inline-flex size-9 cursor-pointer items-center justify-center rounded-xl text-mid-gray transition-colors hover:bg-destrutivo/5 hover:text-destrutivo focus-visible:outline-2 focus-visible:outline-ink disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-mid-gray"
                                        x-on:click="remover(indice)" :disabled="itens.length === 1"
                                        :aria-label="`Remover item ${indice + 1}`" title="Remover item">
                                    <x-phosphor-trash class="size-[18px]" aria-hidden="true" />
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <datalist id="unidades">
                    @foreach (['UN', 'PC', 'CX', 'KIT', 'PAR', 'M', 'KG', 'L', 'RL', 'PCT'] as $unidade)
                        <option value="{{ $unidade }}"></option>
                    @endforeach
                </datalist>

                <div class="flex flex-wrap items-center gap-3 pt-3">
                    <button type="button" class="botao botao-secundario" x-on:click="adicionar()">
                        <x-phosphor-plus class="size-4" aria-hidden="true" />Adicionar item
                    </button>
                    @error('itens')
                        <p class="text-sm font-medium text-destrutivo">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section aria-labelledby="motivo-titulo">
                <h2 id="motivo-titulo" class="titulo-secao">Para que é o material</h2>
                <div class="space-y-5">
                    <div>
                        <label for="finalidade" class="rotulo">Finalidade</label>
                        <p id="finalidade-ajuda" class="ajuda">Vai ser usado para quê? Ex.: montagem do pedido 88412, reparo da impressora do balcão.</p>
                        <textarea id="finalidade" name="finalidade" rows="3" maxlength="2000" required
                                  aria-describedby="finalidade-ajuda"
                                  class="campo mt-2 @error('finalidade') campo-erro @enderror">{{ old('finalidade') }}</textarea>
                        @error('finalidade')
                            <p class="erro-campo">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="justificativa" class="rotulo">Justificativa</label>
                        <p id="justificativa-ajuda" class="ajuda">Por que o material precisa sair do estoque?</p>
                        <textarea id="justificativa" name="justificativa" rows="3" maxlength="2000" required
                                  aria-describedby="justificativa-ajuda"
                                  class="campo mt-2 @error('justificativa') campo-erro @enderror">{{ old('justificativa') }}</textarea>
                        @error('justificativa')
                            <p class="erro-campo">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section x-show="tipo === 'TESTE'" x-cloak aria-labelledby="devolucao-titulo">
                <h2 id="devolucao-titulo" class="titulo-secao">Devolução</h2>
                <div>
                    <label for="data_prevista_devolucao" class="rotulo">Data prevista de devolução</label>
                    <p id="devolucao-ajuda" class="ajuda">
                        Até {{ $limiteDevolucao->format('d/m/Y') }} ({{ $prazoDias }} dias úteis a partir de hoje). Depois disso a requisição aparece como vencida.
                    </p>
                    <input type="date" id="data_prevista_devolucao" name="data_prevista_devolucao"
                           value="{{ is_string(old('data_prevista_devolucao')) ? old('data_prevista_devolucao') : '' }}"
                           min="{{ now()->toDateString() }}" max="{{ $limiteDevolucao->toDateString() }}"
                           :disabled="tipo !== 'TESTE'" :required="tipo === 'TESTE'"
                           aria-describedby="devolucao-ajuda"
                           class="campo mt-2 w-48 tabular-nums @error('data_prevista_devolucao') campo-erro @enderror">
                    @error('data_prevista_devolucao')
                        <p class="erro-campo">{{ $message }}</p>
                    @enderror
                </div>
            </section>
        </div>

        {{-- Resumo: no computador fica fixo ao lado, com o botão de enviar sempre à vista. --}}
        <aside class="quadro p-5 lg:sticky lg:top-8">
            <h2 class="titulo-secao">Resumo</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-mid-gray">Tipo</dt>
                    <dd>
                        <span x-show="!tipo" class="text-mid-gray">Não escolhido</span>
                        <span x-show="tipo === 'TESTE'" x-cloak><x-selo-tipo :tipo="\App\Enums\TipoRequisicao::TESTE" /></span>
                        <span x-show="tipo === 'USO_CONSUMO'" x-cloak><x-selo-tipo :tipo="\App\Enums\TipoRequisicao::USO_CONSUMO" /></span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-mid-gray">Itens preenchidos</dt>
                    <dd class="font-medium tabular-nums" x-text="itens.filter((item) => item.descricao.trim() !== '').length"></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-mid-gray">Setor</dt>
                    <dd class="font-medium">{{ auth()->user()->setor->nome }}</dd>
                </div>
            </dl>
            <div class="mt-5 grid gap-2 border-t border-hairline pt-5">
                <button type="submit" class="botao botao-primario w-full" :disabled="enviando">
                    <x-phosphor-circle-notch x-show="enviando" x-cloak class="size-[18px] animate-spin" aria-hidden="true" />
                    <x-phosphor-signature x-show="!enviando" class="size-[18px]" aria-hidden="true" />
                    <span x-text="enviando ? 'Enviando…' : 'Assinar e enviar'"></span>
                </button>
                <a href="{{ route('requisicoes.index') }}" class="botao w-full border-transparent text-mid-gray hover:bg-surface-alt">Voltar</a>
            </div>
        </aside>
    </form>
</x-layouts.app>
