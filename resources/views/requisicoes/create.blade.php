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

    <div class="mb-4">
        <h1 class="text-lg font-semibold">Nova requisição</h1>
        <p class="text-sm text-slate-600">Preencha, confira e assine. A requisição vai para a aprovação do seu setor.</p>
    </div>

    <form method="POST" action="{{ route('requisicoes.store') }}" class="max-w-4xl space-y-4"
          x-data="novaRequisicao(@js([
              'tipo' => $tipoAnterior,
              'itens' => $itensAnteriores,
              'erros' => (object) $errosDosItens,
              'abrirAssinatura' => $errors->has('senha'),
          ]))"
          x-on:submit="aoEnviar($event)">
        @csrf

        <fieldset class="painel p-4">
            <legend class="float-left mb-3 w-full font-semibold">Tipo de requisição</legend>
            <div class="clear-both grid gap-3 sm:grid-cols-2">
                <label class="flex cursor-pointer flex-col rounded-sm border-2 p-4 transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-marinho-700"
                       :class="tipo === 'TESTE' ? 'border-sky-700 bg-sky-50' : 'border-slate-300 bg-white hover:border-slate-400'">
                    <input type="radio" name="tipo" value="TESTE" x-model="tipo" required class="sr-only">
                    <span class="flex items-center gap-2 font-semibold tracking-wide text-sky-900">
                        <x-phosphor-arrow-u-up-left-bold class="size-5" aria-hidden="true" />
                        TESTE
                    </span>
                    <span class="mt-1.5 text-sm text-slate-700">O material sai para teste e volta ao estoque em até {{ $prazoDias }} dias úteis.</span>
                </label>

                <label class="flex cursor-pointer flex-col rounded-sm border-2 p-4 transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-marinho-700"
                       :class="tipo === 'USO_CONSUMO' ? 'border-amber-600 bg-amber-50' : 'border-slate-300 bg-white hover:border-slate-400'">
                    <input type="radio" name="tipo" value="USO_CONSUMO" x-model="tipo" required class="sr-only">
                    <span class="flex items-center gap-2 font-semibold tracking-wide text-amber-950">
                        <x-phosphor-package-bold class="size-5" aria-hidden="true" />
                        USO E CONSUMO
                    </span>
                    <span class="mt-1.5 text-sm text-slate-700">O material é consumido e não volta. Passa pela liberação do líder do estoque e pela baixa no WinThor.</span>
                </label>
            </div>
            @error('tipo')
                <p class="erro-campo">{{ $message }}</p>
            @enderror
        </fieldset>

        <section class="painel" aria-labelledby="itens-titulo">
            <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <h2 id="itens-titulo" class="font-semibold">Itens</h2>
                <span class="text-sm text-slate-600" x-text="itens.length === 1 ? '1 item' : itens.length + ' itens'"></span>
            </div>

            {{-- No computador é uma tabela; no celular cada item vira um bloco com os rótulos visíveis. --}}
            <div class="hidden grid-cols-[2rem_minmax(0,1fr)_8rem_7rem_2rem] gap-3 border-b border-slate-300 bg-slate-50 px-4 py-2 text-xs font-medium text-slate-600 sm:grid">
                <span class="text-right">#</span>
                <span>Descrição do produto</span>
                <span class="text-right">Quantidade</span>
                <span>Unidade</span>
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
                                    class="inline-flex size-8 cursor-pointer items-center justify-center rounded-sm text-slate-600 hover:bg-slate-100 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-marinho-700 disabled:cursor-not-allowed disabled:opacity-40 sm:hidden"
                                    x-on:click="remover(indice)" :disabled="itens.length === 1"
                                    :aria-label="`Remover item ${indice + 1}`">
                                <x-phosphor-trash class="size-4" aria-hidden="true" />
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
                                    class="inline-flex size-8 cursor-pointer items-center justify-center rounded-sm text-slate-600 hover:bg-slate-100 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-marinho-700 disabled:cursor-not-allowed disabled:opacity-40"
                                    x-on:click="remover(indice)" :disabled="itens.length === 1"
                                    :aria-label="`Remover item ${indice + 1}`">
                                <x-phosphor-trash class="size-4" aria-hidden="true" />
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

            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 px-4 py-3">
                <button type="button" class="botao botao-secundario" x-on:click="adicionar()">
                    <x-phosphor-plus class="size-4" aria-hidden="true" />
                    Adicionar item
                </button>
                @error('itens')
                    <p class="text-xs font-medium text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </section>

        <section class="painel space-y-4 p-4" aria-labelledby="motivo-titulo">
            <h2 id="motivo-titulo" class="font-semibold">Para que é o material</h2>

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
        </section>

        <section class="painel p-4" x-show="tipo === 'TESTE'" x-cloak aria-labelledby="devolucao-titulo">
            <h2 id="devolucao-titulo" class="font-semibold">Devolução</h2>
            <div class="mt-3">
                <label for="data_prevista_devolucao" class="rotulo">Data prevista de devolução</label>
                <p id="devolucao-ajuda" class="ajuda">
                    Até {{ $limiteDevolucao->format('d/m/Y') }}, {{ $prazoDias }} dias úteis a partir de hoje. Depois disso a requisição aparece como vencida.
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

        <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
            <a href="{{ route('requisicoes.index') }}" class="botao botao-secundario">Voltar</a>
            <button type="button" class="botao botao-primario" x-on:click="abrirAssinatura()">
                <x-phosphor-signature class="size-4" aria-hidden="true" />
                Assinar e enviar
            </button>
        </div>

        <div x-show="assinando" x-cloak
             class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-950/50 px-4 pt-[12vh] pb-8"
             role="dialog" aria-modal="true" aria-labelledby="assinatura-titulo"
             x-on:keydown.escape.window="assinando = false">
            <div class="w-full max-w-md rounded-sm border border-slate-300 bg-white shadow-xl shadow-slate-950/10" x-on:click.outside="assinando = false">
                <div class="border-b border-slate-200 px-5 py-3.5">
                    <h2 id="assinatura-titulo" class="font-semibold">Assinar requisição</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
                    <p class="text-sm text-slate-700">Confirme com a sua senha. A assinatura fica registrada com o seu nome, a data e a hora.</p>
                    <div>
                        <label for="senha" class="rotulo">Sua senha</label>
                        <input id="senha" name="senha" type="password" x-ref="senha" autocomplete="new-password" :required="assinando"
                               class="campo mt-1.5 @error('senha') campo-erro @enderror">
                        @error('senha')
                            <p class="erro-campo">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                    <button type="button" class="botao botao-secundario" x-on:click="assinando = false">Voltar</button>
                    <button type="submit" class="botao botao-primario" :disabled="enviando">Assinar e enviar</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
