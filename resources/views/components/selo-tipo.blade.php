@props(['tipo'])
@php($teste = $tipo === \App\Enums\TipoRequisicao::TESTE)
{{-- Teste e Uso e Consumo nunca se diferenciam só pela cor: texto e ícone também mudam. --}}
<span {{ $attributes->class([
    'inline-flex items-center gap-1 rounded-sm border px-1.5 py-0.5 text-[11px] leading-4 font-semibold tracking-wide whitespace-nowrap',
    'border-sky-300 bg-sky-50 text-sky-900' => $teste,
    'border-amber-400 bg-amber-100 text-amber-950' => ! $teste,
]) }}>
    @if ($teste)
        <x-phosphor-arrow-u-up-left-bold class="size-3.5" aria-hidden="true" />
    @else
        <x-phosphor-package-bold class="size-3.5" aria-hidden="true" />
    @endif
    {{ $tipo->rotulo() }}
</span>
