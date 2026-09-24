@props(['href', 'ativo' => false, 'icone', 'contador' => null])
<a href="{{ $href }}"
   @if ($ativo) aria-current="page" @endif
   @class([
       'flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-white',
       'bg-white/15 text-white' => $ativo,
       'text-white/70 hover:bg-white/10 hover:text-white' => ! $ativo,
   ])>
    <x-dynamic-component :component="'phosphor-'.$icone" class="size-[18px] shrink-0" aria-hidden="true" />
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    @if ($contador > 0)
        <span class="min-w-5 shrink-0 rounded-md bg-white px-1.5 text-center text-xs leading-5 font-semibold text-marinho-900 tabular-nums">{{ $contador }}</span>
    @endif
</a>
