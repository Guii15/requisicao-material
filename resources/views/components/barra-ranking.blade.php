@props(['itens'])
{{-- Lista ranqueada, maior primeiro: nome à esquerda, número à direita. A ordem já diz quem tem mais. --}}
<ol class="divide-y divide-hairline">
    @forelse ($itens as $item)
        <li class="flex items-baseline justify-between gap-3 py-2.5 first:pt-0">
            <span class="truncate text-ink">{{ $item['rotulo'] }}</span>
            <span class="shrink-0 font-medium tabular-nums text-ink">{{ $item['valor'] }}</span>
        </li>
    @empty
        <li class="text-mid-gray">Sem dados ainda.</li>
    @endforelse
</ol>
