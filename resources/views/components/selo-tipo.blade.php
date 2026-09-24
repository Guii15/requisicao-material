@props(['tipo'])
@php($teste = $tipo === \App\Enums\TipoRequisicao::TESTE)
{{-- Barra lateral de cor (sem fundo preenchido): Teste em azul, Uso e Consumo em âmbar.
     A cor é só a marca; o texto (neutro) é que informa, para nunca depender só da cor. --}}
<span {{ $attributes->class(['inline-flex items-center gap-2 text-sm font-medium whitespace-nowrap text-ink-soft lowercase first-letter:uppercase']) }}>
    <span @class(['h-3.5 w-1 shrink-0', 'bg-sky-600' => $teste, 'bg-amber-600' => ! $teste]) aria-hidden="true"></span>
    {{ $tipo->rotulo() }}
</span>
