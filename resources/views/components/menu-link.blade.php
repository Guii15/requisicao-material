@props(['href', 'ativo' => false, 'contador' => null, 'contadorNome' => null])
<a href="{{ $href }}"
   @if ($ativo) aria-current="page" @endif
   @if ($contadorNome) data-contador-{{ $contadorNome }}="{{ $contador }}" @endif
   @class([
       'inline-flex h-9 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-white',
       'bg-white/15 text-white' => $ativo,
       'text-white/75 hover:bg-white/10 hover:text-white' => ! $ativo,
   ])>
    {{ $slot }}
    @if ($contador > 0)
        <span class="min-w-5 rounded-full bg-white px-1.5 text-center text-xs leading-5 font-semibold text-marinho-900 tabular-nums">{{ $contador }}</span>
    @endif
</a>
