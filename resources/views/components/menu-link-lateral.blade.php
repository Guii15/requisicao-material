@props(['href', 'ativo' => false, 'contador' => null])
<a href="{{ $href }}"
   @if ($ativo) aria-current="page" @endif
   @class([
       'flex items-center gap-2 rounded-xl px-3 py-2 text-sm transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ink',
       'bg-surface-alt font-medium text-ink' => $ativo,
       'text-ink-soft hover:bg-fundo hover:text-ink' => ! $ativo,
   ])>
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    @if ($contador > 0)
        <span class="min-w-5 shrink-0 rounded-full bg-ink px-1.5 text-center text-[11px] leading-5 font-medium text-white tabular-nums">{{ $contador }}</span>
    @endif
</a>
