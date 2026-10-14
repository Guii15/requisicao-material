@props(['itens', 'acao'])
{{-- Decisão item a item: tudo vem aprovado; quem decide marca "Recusar" nos que não devem seguir e diz o motivo.
     Só aparece quando há mais de um item (com um só, "Reprovar" já resolve). --}}
@if ($itens->count() > 1)
    @php
        $iniciais = $itens->mapWithKeys(fn ($item) => [
            $item->id => old('_acao') === $acao && old("itens.{$item->id}.decisao") === 'recusar' ? 'recusar' : 'aprovar',
        ])->all();
    @endphp
    <fieldset class="space-y-2" x-data="{ decisao: @js($iniciais) }">
        <legend class="rotulo">Decisão por item</legend>
        <p class="ajuda">Todos os itens vêm aprovados. Marque "Recusar" nos que não devem seguir e diga o motivo. Se recusar todos, a requisição inteira é reprovada.</p>
        <div class="mt-2 space-y-2">
            @foreach ($itens as $item)
                <div class="rounded-lg border border-hairline p-3" :class="decisao[{{ $item->id }}] === 'recusar' ? 'border-destrutivo' : ''">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                        <p class="min-w-0 flex-1 text-sm font-medium text-ink">
                            @if ($item->codigo)<span class="mr-1.5 tabular-nums text-mid-gray">{{ $item->codigo }}</span>@endif{{ $item->descricao }}
                            <span class="ml-1 text-[13px] font-normal text-mid-gray tabular-nums">{{ \App\Support\Quantidade::formatar($item->qtd_solicitada) }} {{ $item->unidade }}</span>
                        </p>
                        <div class="flex shrink-0 gap-2">
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-hairline bg-paper px-3 py-1.5 text-[13px] font-medium text-ink has-[:checked]:border-marca has-[:checked]:bg-marca-suave">
                                <input type="radio" name="itens[{{ $item->id }}][decisao]" value="aprovar" x-model="decisao[{{ $item->id }}]" class="accent-marca">
                                Aprovar
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-hairline bg-paper px-3 py-1.5 text-[13px] font-medium text-ink has-[:checked]:border-destrutivo has-[:checked]:text-destrutivo">
                                <input type="radio" name="itens[{{ $item->id }}][decisao]" value="recusar" x-model="decisao[{{ $item->id }}]" class="accent-destrutivo">
                                Recusar
                            </label>
                        </div>
                    </div>
                    <div x-show="decisao[{{ $item->id }}] === 'recusar'" x-cloak class="mt-2.5">
                        <label for="{{ $acao }}-motivo-{{ $item->id }}" class="sr-only">Motivo da recusa de {{ $item->descricao }}</label>
                        <input id="{{ $acao }}-motivo-{{ $item->id }}" type="text" name="itens[{{ $item->id }}][motivo]" maxlength="1000"
                               placeholder="Motivo da recusa (o solicitante vai ver)"
                               value="{{ old('_acao') === $acao && is_string(old("itens.{$item->id}.motivo")) ? old("itens.{$item->id}.motivo") : '' }}"
                               :required="decisao[{{ $item->id }}] === 'recusar'" :disabled="decisao[{{ $item->id }}] !== 'recusar'"
                               class="campo @error("itens.{$item->id}.motivo") campo-erro @enderror">
                        @error("itens.{$item->id}.motivo")
                            <p class="erro-campo">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endforeach
        </div>
    </fieldset>
@endif
