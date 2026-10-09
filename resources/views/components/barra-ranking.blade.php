@props(['itens'])
{{-- Ranking com barra: o comprimento é proporcional ao maior valor da lista, então dá para comparar de relance. --}}
@php $maior = max(1, collect($itens)->max('valor') ?? 1); @endphp
<ol class="space-y-4">
    @forelse ($itens as $item)
        <li>
            <div class="flex items-baseline justify-between gap-3">
                <span class="truncate text-ink">{{ $item['rotulo'] }}</span>
                <span class="shrink-0 text-lg font-semibold tabular-nums tracking-tight text-ink">{{ $item['valor'] }}</span>
            </div>
            <div class="mt-1.5 h-2 bg-hairline"><div class="h-full {{ $loop->first ? 'bg-marca' : 'bg-ink' }}" style="width: {{ round($item['valor'] / $maior * 100) }}%"></div></div>
        </li>
    @empty
        <li class="text-mid-gray">Sem dados ainda.</li>
    @endforelse
</ol>
