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
        <h1 class="text-2xl font-semibold tracking-tight">Nova requisição</h1>
        <p class="mt-1 text-sm text-slate-500">Peça material ao estoque. Depois de enviar, a requisição vai para a aprovação do seu setor.</p>
    </div>

    <form method="POST" action="{{ route('requisicoes.store') }}"
          class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start"
          x-data="novaRequisicao(@js([
              'tipo' => $tipoAnterior,
              'itens' => $itensAnteriores,
              'erros' => (object) $errosDosItens,
              'abrirAssinatura' => $errors->has('senha'),
          ]))"
          x-on:submit="aoEnviar($event)">
        @csrf

        <div class="space-y-6">
            <fieldset class="quadro">
                <legend class="quadro-titulo float-left w-full">Tipo de retirada</legend>
                <div class="clear-both grid gap-3 p-5 sm:grid-cols-2">
                    <label class="relative flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors"
                           :class="tipo === 'TESTE' ? 'border-sky-500 bg-sky-50/60 ring-1 ring-sky-500' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'">
                        <input type="radio" name="tipo" value="TESTE" x-model="tipo" required
                               class="mt-0.5 size-4 border-slate-400 text-sky-700 focus:ring-sky-700">
                        <span>
                            <span class="flex items-center gap-2 font-semibold text-slate-900">
                                <x-phosphor-arrows-clockwise class="size-5 text-sky-700" aria-hidden="true" />
                                Teste
                            </span>
                            <span class="mt-1 block text-sm text-slate-600">O material sai para teste e volta ao estoque em até {{ $prazoDias }} dias úteis.</span>
                        </span>
                    </label>
                    <label class="relative flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors"
                           :class="tipo === 'USO_CONSUMO' ? 'border-amber-500 bg-amber-50/60 ring-1 ring-amber-500' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'">
                        <input type="radio" name="tipo" value="USO_CONSUMO" x-model="tipo" required
                               class="mt-0.5 size-4 border-slate-400 text-amber-600 focus:ring-amber-600">
                        <span>
                            <span class="flex items-center gap-2 font-semibold text-slate-900">
                                <x-phosphor-package class="size-5 text-amber-600" aria-hidden="true" />
                                Uso e consumo
                            </span>
                            <span class="mt-1 block text-sm text-slate-600">O material é consumido e não volta. Passa pela liberação do líder do estoque e pela baixa no WinThor.</span>
                        </span>
                    </label>
                </div>
                @error('tipo')
                    <p class="erro-campo px-5 pb-4">{{ $message }}</p>
                @enderror
            </fieldset>

            <section class="quadro" aria-labelledby="itens-titulo">
                <h2 id="itens-titulo" class="quadro-titulo flex items-baseline justify-between">
                    <span>Itens</span>
                    <span class="text-sm font-normal text-slate-500" x-text="itens.length === 1 ? '1 item' : itens.length + ' itens'"></span>
                </h2>

                {{-- No computador é uma grade; no celular cada item vira um bloco com os rótulos visíveis. --}}
                <div class="hidden grid-cols-[1.5rem_minmax(0,1fr)_7rem_6rem_2.25rem] gap-3 px-5 pt-4 pb-1 sm:grid">
                    <span class="legenda text-right">#</span>
                    <span class="legenda">Descrição do produto</span>
                    <span class="legenda text-right">Quantidade</span>
                    <span class="legenda">Unidade</span>
                    <span class="sr-only">Remover</span>
                </div>

                <div class="divide-y divide-slate-100 sm:divide-y-0">
                    <template x-for="(item, indice) in itens" :key="item.chave">
                        <div class="grid grid-cols-2 gap-x-3 gap-y-2 px-5 py-4 sm:grid-cols-[1.5rem_minmax(0,1fr)_7rem_6rem_2.25rem] sm:items-start sm:py-1.5">
                            <div class="col-span-2 flex items-center justify-between sm:col-span-1 sm:block sm:pt-2.5 sm:text-right">
                                <span class="text-sm font-medium text-slate-700 sm:font-normal sm:text-slate-400 sm:tabular-nums">
                                    <span class="sm:hidden">Item </span><span x-text="indice + 1"></span>
                                </span>
                                <button type="button"
                                        class="cursor-pointer text-sm font-medium text-red-700 hover:underline disabled:cursor-not-allowed disabled:text-slate-400 disabled:no-underline sm:hidden"
                                        x-on:click="remover(indice)" :disabled="itens.length === 1"
                                        :aria-label="`Remover item ${indice + 1}`">
                                    Remover
                                </button>
                            </div>

                            <div class="col-span-2 sm:col-span-1">
                                <label :for="`item-${item.chave}-descricao`" class="text-sm text-slate-600 sm:sr-only">Descrição do produto</label>
                                <input type="text" data-descricao maxlength="255" required
                                       class="campo mt-1 sm:mt-0" :class="erroDoItem(indice, 'descricao') && 'campo-erro'"
                                       :id="`item-${item.chave}-descricao`"
                                       :name="`itens[${indice}][descricao]`" x-model="item.descricao"
                                       placeholder="Ex.: SSD NVMe 1TB Kingston NV2">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'descricao')" x-text="erroDoItem(indice, 'descricao')"></p>
                            </div>

                            <div>
                                <label :for="`item-${item.chave}-quantidade`" class="text-sm text-slate-600 sm:sr-only">Quantidade</label>
                                <input type="text" inputmode="decimal" required
                                       class="campo mt-1 text-right tabular-nums sm:mt-0" :class="erroDoItem(indice, 'quantidade') && 'campo-erro'"
                                       :id="`item-${item.chave}-quantidade`"
                                       :name="`itens[${indice}][quantidade]`" x-model="item.quantidade">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'quantidade')" x-text="erroDoItem(indice, 'quantidade')"></p>
                            </div>

                            <div>
                                <label :for="`item-${item.chave}-unidade`" class="text-sm text-slate-600 sm:sr-only">Unidade</label>
                                <input type="text" list="unidades" maxlength="20" required
                                       class="campo mt-1 uppercase sm:mt-0" :class="erroDoItem(indice, 'unidade') && 'campo-erro'"
                                       :id="`item-${item.chave}-unidade`"
                                       :name="`itens[${indice}][unidade]`" x-model="item.unidade">
                                <p class="erro-campo" x-show="erroDoItem(indice, 'unidade')" x-text="erroDoItem(indice, 'unidade')"></p>
                            </div>

                            <div class="hidden sm:block">
                                <button type="button"
                                        class="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-red-50 hover:text-red-700 focus-visible:outline-2 focus-visible:outline-marinho-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-400"
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

                <div class="flex flex-wrap items-center gap-3 px-5 pt-3 pb-5">
                    <button type="button" class="botao botao-secundario" x-on:click="adicionar()">
                        <x-phosphor-plus class="size-4" aria-hidden="true" />Adicionar item
                    </button>
                    @error('itens')
                        <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                    @enderror
                </div>
            </section>

            <section class="quadro" aria-labelledby="motivo-titulo">
                <h2 id="motivo-titulo" class="quadro-titulo">Para que é o material</h2>
                <div class="space-y-5 p-5">
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

            <section class="quadro" x-show="tipo === 'TESTE'" x-cloak aria-labelledby="devolucao-titulo">
                <h2 id="devolucao-titulo" class="quadro-titulo">Devolução</h2>
                <div class="p-5">
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
        <aside class="quadro p-5 lg:sticky lg:top-24">
            <h2 class="font-semibold text-slate-900">Resumo</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Tipo</dt>
                    <dd>
                        <span x-show="!tipo" class="text-slate-400">Não escolhido</span>
                        <span x-show="tipo === 'TESTE'" x-cloak><x-selo-tipo :tipo="\App\Enums\TipoRequisicao::TESTE" /></span>
                        <span x-show="tipo === 'USO_CONSUMO'" x-cloak><x-selo-tipo :tipo="\App\Enums\TipoRequisicao::USO_CONSUMO" /></span>
                    </dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Itens preenchidos</dt>
                    <dd class="font-medium tabular-nums" x-text="itens.filter((item) => item.descricao.trim() !== '').length"></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500">Setor</dt>
                    <dd class="font-medium">{{ auth()->user()->setor->nome }}</dd>
                </div>
            </dl>
            <div class="mt-5 grid gap-2 border-t border-slate-100 pt-5">
                <button type="button" class="botao botao-primario w-full" x-on:click="abrirAssinatura()">
                    <x-phosphor-signature class="size-[18px]" aria-hidden="true" />Assinar e enviar
                </button>
                <a href="{{ route('requisicoes.index') }}" class="botao w-full border-transparent text-slate-600 hover:bg-slate-100">Voltar</a>
            </div>
        </aside>

        <div x-show="assinando" x-cloak
             class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-950/40 px-4 pt-[12vh] pb-8 backdrop-blur-[2px]"
             role="dialog" aria-modal="true" aria-labelledby="assinatura-titulo"
             x-on:keydown.escape.window="assinando = false">
            <div class="w-full max-w-md overflow-hidden rounded-xl bg-white shadow-xl shadow-slate-950/15 ring-1 ring-slate-200" x-on:click.outside="assinando = false">
                <div class="space-y-4 p-5">
                    <div>
                        <h2 id="assinatura-titulo" class="text-lg font-semibold text-slate-900">Assinar requisição</h2>
                        <p class="mt-1 text-sm text-slate-600">Digite a sua senha. A assinatura fica registrada com o seu nome, a data e a hora.</p>
                    </div>
                    <div>
                        <label for="senha" class="rotulo">Sua senha</label>
                        <input id="senha" name="senha" type="password" x-ref="senha" autocomplete="new-password" :required="assinando"
                               class="campo mt-2 @error('senha') campo-erro @enderror">
                        @error('senha')
                            <p class="erro-campo">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
                    <button type="button" class="botao botao-secundario" x-on:click="assinando = false">Fechar</button>
                    <button type="submit" class="botao botao-primario" :disabled="enviando">Assinar e enviar</button>
                </div>
            </div>
        </div>
    </form>
</x-layouts.app>
