@props(['tipo'])
@php($teste = $tipo === \App\Enums\TipoRequisicao::TESTE)
{{-- Carimbo do tipo. Uso e Consumo é preenchido para não ser confundido com Teste nem de relance. --}}
<span {{ $attributes->class([
    'inline-block border px-1.5 text-[11px] leading-[18px] font-semibold tracking-wider whitespace-nowrap uppercase',
    'border-sky-800 bg-white text-sky-900' => $teste,
    'border-amber-700 bg-amber-100 text-amber-950' => ! $teste,
]) }}>{{ $tipo->rotulo() }}</span>
