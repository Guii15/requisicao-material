@props(['icone', 'titulo'])
{{-- Lista vazia: diz o que aconteceu e, quando houver, o próximo passo (slot "acao"). --}}
<div {{ $attributes->class(['quadro flex flex-col items-center px-6 py-14 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-md bg-surface-alt text-ink" aria-hidden="true">
        <x-dynamic-component :component="'phosphor-' . $icone" class="size-6" />
    </span>
    <p class="mt-4 font-semibold text-ink">{{ $titulo }}</p>
    @if ($slot->isNotEmpty())
        <p class="mt-1 max-w-sm text-sm text-mid-gray">{{ $slot }}</p>
    @endif
    @isset($acao)
        <div class="mt-5">{{ $acao }}</div>
    @endisset
</div>
