@props(['icone' => 'tray', 'titulo'])
{{-- Lista vazia: ícone numa bolinha, o que aconteceu e, quando houver, o próximo passo (slot "acao"). --}}
<div {{ $attributes->class(['quadro px-5 py-16 text-center']) }}>
    <span class="mx-auto mb-4 grid size-14 place-items-center rounded-full bg-surface-alt text-mid-gray">
        <x-dynamic-component :component="'phosphor-'.($icone ?: 'tray')" class="size-6" aria-hidden="true" />
    </span>
    <p class="font-display text-[15px] font-bold text-ink">{{ $titulo }}</p>
    @if ($slot->isNotEmpty())
        <p class="mx-auto mt-2 max-w-md text-[13px] text-mid-gray">{{ $slot }}</p>
    @endif
    @isset($acao)
        <div class="mt-5">{{ $acao }}</div>
    @endisset
</div>
