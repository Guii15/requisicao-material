@props(['icone' => null, 'titulo'])
{{-- Lista vazia: diz o que aconteceu e, quando houver, o próximo passo (slot "acao"). Sem ícone decorativo. --}}
<div {{ $attributes->class(['quadro px-6 py-8']) }}>
    <p class="text-[15px] font-semibold tracking-tight text-ink">{{ $titulo }}</p>
    @if ($slot->isNotEmpty())
        <p class="mt-1 max-w-md text-sm text-mid-gray">{{ $slot }}</p>
    @endif
    @isset($acao)
        <div class="mt-4">{{ $acao }}</div>
    @endisset
</div>
