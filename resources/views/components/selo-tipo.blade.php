@props(['tipo'])
@php($teste = $tipo === \App\Enums\TipoRequisicao::TESTE)
{{-- Selo do tipo. Teste em azul, Uso e Consumo em âmbar: não se confundem nem de relance. --}}
<span {{ $attributes->class([
    'inline-block rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap lowercase ring-1 ring-inset first-letter:uppercase',
    'bg-sky-50 text-sky-800 ring-sky-200' => $teste,
    'bg-amber-50 text-amber-800 ring-amber-200' => ! $teste,
]) }}>{{ $tipo->rotulo() }}</span>
