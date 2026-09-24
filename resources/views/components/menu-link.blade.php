@props(['href', 'ativo' => false, 'contador' => null, 'contadorNome' => null])
<a href="{{ $href }}"
   @if ($ativo) aria-current="page" @endif
   @if ($contadorNome) data-contador-{{ $contadorNome }}="{{ $contador }}" @endif
   @class([
       'relative inline-flex h-11 shrink-0 items-center gap-2 px-3 whitespace-nowrap md:h-12 transition-colors focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-white',
       'text-white after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:bg-white' => $ativo,
       'text-white/70 hover:text-white' => ! $ativo,
   ])>
    {{ $slot }}
    @if ($contador > 0)
        <span class="min-w-5 bg-white px-1 text-center font-mono text-[11px] leading-[18px] font-semibold text-marinho-900">{{ $contador }}</span>
    @endif
</a>
