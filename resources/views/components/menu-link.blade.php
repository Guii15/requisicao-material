@props(['href', 'ativo' => false, 'contador' => null, 'contadorNome' => null])
<a href="{{ $href }}"
   @if ($ativo) aria-current="page" @endif
   @class([
       'relative inline-flex h-11 shrink-0 items-center gap-2 px-3 whitespace-nowrap md:h-14 transition-colors focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-white',
       'text-white after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:bg-white' => $ativo,
       'text-white/70 hover:text-white' => ! $ativo,
   ])>
    {{ $slot }}
    @if ($contador !== null)
        <span @if ($contadorNome) data-contador-{{ $contadorNome }}="{{ $contador }}" @endif
              @class([
                  'min-w-5 rounded-sm px-1 py-px text-center font-mono text-xs',
                  'bg-white text-marinho-900' => $contador > 0,
                  'bg-white/15 text-white/70' => $contador === 0,
              ])>{{ $contador }}</span>
    @endif
</a>
