@props(['href', 'ativo' => false, 'contador' => null, 'contadorNome' => null, 'icone' => 'circle'])
<a href="{{ $href }}"
   @if ($ativo) aria-current="page" @endif
   @if ($contadorNome) data-contador-{{ $contadorNome }}="{{ $contador }}" @endif
   @class([
       'relative mb-1 flex h-10 items-center gap-3 rounded-md px-3.5 text-[13px] transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-marca',
       'bg-preto-2 font-semibold text-white before:absolute before:top-[7px] before:left-0 before:h-6 before:w-[3px] before:rounded-r-[3px] before:bg-white' => $ativo,
       'font-medium text-preto-texto hover:bg-preto-2 hover:text-white' => ! $ativo,
   ])>
    <x-dynamic-component :component="'phosphor-'.$icone" :class="'size-[17px] shrink-0'.($ativo ? ' text-white' : '')" aria-hidden="true" />
    <span class="min-w-0 flex-1 truncate">{{ $slot }}</span>
    @if ($contador > 0)
        <span class="grid h-5 min-w-5 shrink-0 place-items-center rounded-[4px] bg-white px-1.5 text-[10px] font-semibold text-marca tabular-nums">{{ $contador }}</span>
    @endif
</a>
