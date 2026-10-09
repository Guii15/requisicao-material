@props(['tipo'])
@php($teste = $tipo === \App\Enums\TipoRequisicao::TESTE)
@php($compra = $tipo === \App\Enums\TipoRequisicao::COMPRA_FUNCIONARIO)
{{-- Teste volta ao estoque (frasco); Uso e consumo sai e não volta (caixa). Só ícone e texto, sem fundo. --}}
<span {{ $attributes->class(['inline-flex items-center gap-1.5 text-[12px] whitespace-nowrap text-mid-gray']) }}>
    @if ($teste)
        <x-phosphor-flask class="size-3.5" aria-hidden="true" />
    @elseif ($compra)
        <x-phosphor-shopping-cart class="size-3.5" aria-hidden="true" />
    @else
        <x-phosphor-package class="size-3.5" aria-hidden="true" />
    @endif
    {{ ucfirst(mb_strtolower($tipo->rotulo())) }}
</span>
