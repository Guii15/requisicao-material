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

    <h1 class="mb-4 text-xl font-semibold">Nova requisição</h1>

    <form method="POST" action="{{ route('requisicoes.store') }}" class="max-w-4xl"
          x-data="novaRequisicao(@js([
              'tipo' => $tipoAnterior,
              'itens' => $itensAnteriores,
              'erros' => (object) $errosDosItens,
              'abrirAssinatura' => $errors->has('senha'),
          ]))"
          x-on:submit="aoEnviar($event)">
        @csrf

        <div class="quadro">
            <fieldset>
                <legend class="quadro-titulo float-left w-full">1. Tipo de retirada</legend>
                <div class="clear-both divide-y divide-slate-200">
                    <label class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-slate-50" :class="tipo === 'TESTE' && 'bg-sky-50 hover:bg-sky-50'">
                        <input type="radio" name="tipo" value="TESTE" x-model="tipo" required
                               class="mt-0.5 size-4 border-slate-500 text-sky-800 focus:ring-sky-800">
                        <span>
                            <x-selo-tipo :tipo="\App\Enums\TipoRequisicao::TESTE" />
                            <span class="mt-1 block text-sm text-slate-700">O material sai para teste e volta ao estoque em até {{ $prazoDias }} dias úteis.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-slate-50" :class="tipo === 'USO_CONSUMO' && 'bg-amber-50 hover:bg-amber-50'">
                        <input type="radio" name="tipo" value="USO_CONSUMO" x-model="tipo" required
                               class="mt-0.5 size-4 border-slate-500 text-amber-700 focus:ring-amber-700">
                        <span>
                            <x-selo-tipo :tipo="\App\Enums\TipoRequisicao::USO_CONSUMO" />
                            <span class="mt-1 block text-sm text-slate-700">O material é consumido e não volta. Passa pela liberação do líder do estoque e pela baixa no WinThor.</span>
                        </span>
                    </label>
                </div>
                @error('tipo')
                    <p class="erro-campo px-4 pb-3">{{ $message }}</p>
                @enderror
            </fieldset>

            <section aria-labelledby="itens-titulo">
                <h2 id="itens-titulo" class="quadro-titulo flex justify-between border-t border-slate-400">
                    <span>2. Itens</span>
                    <span class="font-normal normal-case tracking-normal text-slate-600" x-text="itens.length === 1 ? '1 item' : itens.length + ' itens'"></span>
                </h2>

                {{-- No computador é uma grade; no celular cada item vira um bloco com os rótulos visíveis. --}}
                <div class="hidden grid-cols-[2rem_minmax(0,1fr)_8rem_7rem_2rem] gap-3 border-b border-slate-300 px-4 py-1.5 sm:grid">
                    <span class="legenda text-right">#</span>
                    <span class="legenda">Descrição do produto</span>
                    <span class="legenda text-right">Quantidade</span>
                    <span class="legenda">Unidade</span>
                    <span class="sr-only">Remover</span>
                </div>

                <div class="divide-y divide-slate-200">
                    <template x-for="(item, indice) in itens" :key="item.chave">
                        <div class="grid grid-cols-2 gap-x-3 gap-y-2 px-4 py-3 sm:grid-cols-[2rem_minmax(0,1fr)_8rem_7rem_2rem] sm:items-start sm:py-2">
                            <div class="col-span-2 flex items-center justify-between sm:col-span-1 sm:block sm:pt-2 sm:text-right">
                                <span class="text-xs font-semibold text-slate-700 sm:font-mono sm:text-sm sm:font-normal sm:text-slate-500">
                                    <span class="sm:hidden">Item </span><span x-text="indice + 1"></span>
                                </span>
                                <button type="button"
                                        class="cursor-pointer text-xs font-medium text-red-800 hover:underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline sm:hidden"
                                        x-on:click="remover(indice)" :disabled="itens.length === 1"
                                        :aria-label="`Remover item ${indice + 1}`">
                                    Remover
                                </button>
                            </div>

                            <div class="col-span-2 sm:col-span-1">
                                <label :for="`item-${item.chave}-descricao`" class="text-xs text-slate-600 sm:sr-only">Descrição do produto</label>
                                <input type="text" data-descricao maxlength="255" required
                                       class="campo mt-1 sm:mt-0" :class="erroDoItem(indice, 'descricao') && 'campo-erro'"
                                       :id="`item-${item.chave}-descricao`"
                                       :name="`itens[${indice}][descricao]`" x-model="item.descricao"
                                       placeholder="Ex.: SSD NVMe 1TB Kingston NV2">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'descricao')" x-text="erroDoItem(indice, 'descricao')"></p>
                            </div>

                            <div>
                                <label :for="`item-${item.chave}-quantidade`" class="text-xs text-slate-600 sm:sr-only">Quantidade</label>
                                <input type="text" inputmode="decimal" required
                                       class="campo mt-1 text-right font-mono sm:mt-0" :class="erroDoItem(indice, 'quantidade') && 'campo-erro'"
                                       :id="`item-${item.chave}-quantidade`"
                                       :name="`itens[${indice}][quantidade]`" x-model="item.quantidade">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'quantidade')" x-text="erroDoItem(indice, 'quantidade')"></p>
                            </div>

                            <div>
                                <label :for="`item-${item.chave}-unidade`" class="text-xs text-slate-600 sm:sr-only">Unidade</label>
                                <input type="text" list="unidades" maxlength="20" required
                                       class="campo mt-1 uppercase sm:mt-0" :class="erroDoItem(indice, 'unidade') && 'campo-erro'"
                                       :id="`item-${item.chave}-unidade`"
                                       :name="`itens[${indice}][unidade]`" x-model="item.unidade">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'unidade')" x-text="erroDoItem(indice, 'unidade')"></p>
                            </div>

                            <div class="hidden pt-0.5 text-right sm:block">
                                <button type="button"
                                        class="inline-flex size-8 cursor-pointer items-center justify-center text-slate-500 hover:bg-red-50 hover:text-red-800 focus-visible:outline-2 focus-visible:outline-marinho-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                                        x-on:click="remover(indice)" :disabled="itens.length === 1"
                                        :aria-label="`Remover item ${indice + 1}`" title="Remover item">
                                    <x-phosphor-x class="size-4" aria-hidden="true" />
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

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 px-4 py-2.5">
                    <button type="button" class="botao botao-secundario h-8" x-on:click="adicionar()">Adicionar item</button>
                    @error('itens')
                        <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section aria-labelledby="motivo-titulo">
                <h2 id="motivo-titulo" class="quadro-titulo border-t border-slate-400">3. Para que é o material</h2>
                <div class="space-y-4 px-4 py-3">
                    <div>
                        <label for="finalidade" class="rotulo">Finalidade</label>
                        <p id="finalidade-ajuda" class="ajuda">Vai ser usado para quê? Ex.: montagem do pedido 88412, reparo da impressora do balcão.</p>
                        <textarea id="finalidade" name="finalidade" rows="2" maxlength="2000" required
                                  aria-describedby="finalidade-ajuda"
                                  class="campo mt-1.5 @error('finalidade') campo-erro @enderror">{{ old('finalidade') }}</textarea>
                        @error('finalidade')
                            <p class="erro-campo">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="justificativa" class="rotulo">Justificativa</label>
                        <p id="justificativa-ajuda" class="ajuda">Por que o material precisa sair do estoque?</p>
                        <textarea id="justificativa" name="justificativa" rows="2" maxlength="2000" required
                                  aria-describedby="justificativa-ajuda"
                                  class="campo mt-1.5 @error('justificativa') campo-erro @enderror">{{ old('justificativa') }}</textarea>
                        @error('justificativa')
                            <p class="erro-campo">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </section>

            <section x-show="tipo === 'TESTE'" x-cloak aria-labelledby="devolucao-titulo">
                <h2 id="devolucao-titulo" class="quadro-titulo border-t border-slate-400">4. Devolução</h2>
                <div class="px-4 py-3">
                    <label for="data_prevista_devolucao" class="rotulo">Data prevista de devolução</label>
                    <p id="devolucao-ajuda" class="ajuda">
                        Até {{ $limiteDevolucao->format('d/m/Y') }} ({{ $prazoDias }} dias úteis a partir de hoje). Depois disso a requisição aparece como vencida.
                    </p>
                    <input type="date" id="data_prevista_devolucao" name="data_prevista_devolucao"
                           value="{{ is_string(old('data_prevista_devolucao')) ? old('data_prevista_devolucao') : '' }}"
                           min="{{ now()->toDateString() }}" max="{{ $limiteDevolucao->toDateString() }}"
                           :disabled="tipo !== 'TESTE'" :required="tipo === 'TESTE'"
                           aria-describedby="devolucao-ajuda"
                           class="campo mt-1.5 w-48 font-mono @error('data_prevista_devolucao') campo-erro @enderror">
                    @error('data_prevista_devolucao')
                        <p class="erro-campo">{{ $message }}</p>
                    @enderror
                </div>
            </section>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <a href="{{ route('requisicoes.index') }}" class="botao botao-secundario">Voltar</a>
            <button type="button" class="botao botao-primario" x-on:click="abrirAssinatura()">Assinar e enviar</button>
        </div>

        <div x-show="assinando" x-cloak
             class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-950/50 px-4 pt-[12vh] pb-8"
             role="dialog" aria-modal="true" aria-labelledby="assinatura-titulo"
             x-on:keydown.escape.window="assinando = false">
            <div class="w-full max-w-md border border-slate-500 bg-white shadow-lg shadow-slate-950/20" x-on:click.outside="assinando = false">
                <h2 id="assinatura-titulo" class="quadro-titulo text-slate-800">Assinar requisição</h2>
                <div class="space-y-3 px-4 py-3">
                    <p class="text-sm text-slate-700">Digite a sua senha. A assinatura fica registrada com o seu nome, a data e a hora.</p>
                    <div>
                        <label for="senha" class="rotulo">Sua senha</label>
                        <input id="senha" name="senha" type="password" x-ref="senha" autocomplete="new-password" :required="assinando"
                               class="campo mt-1.5 @error('senha') campo-erro @enderror">
                        @error('senha')
                            <p class="erro-campo">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-300 bg-slate-50 px-4 py-2.5">
                    <button type="button" class="botao botao-secundario" x-on:click="assinando = false">Fechar</button>
                    <button type="submit" class="botao botao-primario" :disabled="enviando">Assinar e enviar</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
