@props(['href', 'ativo' => false, 'contador' => null, 'contadorNome' => null])
<a href="{{ $href }}"
   @if ($ativo) aria-current="page" @endif
   @if ($contadorNome) data-contador-{{ $contadorNome }}="{{ $contador }}" @endif
   @class([
       'inline-flex h-9 shrink-0 items-center gap-2 rounded-full px-3.5 text-sm font-medium whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ink',
       'bg-ink text-white' => $ativo,
       'text-ink-soft hover:bg-surface-alt hover:text-ink' => ! $ativo,
   ])>
    {{ $slot }}
    @if ($contador > 0)
        <span @class(['min-w-5 rounded-full px-1.5 text-center text-[11px] leading-5 font-medium tabular-nums', 'bg-white/20 text-white' => $ativo, 'bg-ink text-white' => ! $ativo])>{{ $contador }}</span>
    @endif
</a>
