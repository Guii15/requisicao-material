@props(['itens', 'corPadrao' => '#171717'])
{{--
    Lista de barras horizontais ranqueadas (maior primeiro). Uma cor só por bloco,
    a menos que cada item já tenha significado próprio (ex.: situação) — aí vem em "cor".
    A barra cresce da esquerda (base quadrada) e arredonda só na ponta; o valor fica
    fora da barra, nunca dentro, pra nunca cortar texto.
--}}
@php($maior = max(1, collect($itens)->max('valor')))
<ol class="space-y-3">
    @forelse ($itens as $item)
        <li>
            <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                <span class="truncate font-medium text-ink-soft">{{ $item['rotulo'] }}</span>
                <span class="shrink-0 tabular-nums text-mid-gray">{{ $item['valor'] }}</span>
            </div>
            <div class="h-2 w-full overflow-hidden bg-hairline/60">
                <div class="h-full rounded-r-sm" style="width: {{ max(4, round($item['valor'] / $maior * 100)) }}%; background-color: {{ $item['cor'] ?? $corPadrao }}"></div>
            </div>
        </li>
    @empty
        <li class="text-sm text-mid-gray">Sem dados ainda.</li>
    @endforelse
</ol>
