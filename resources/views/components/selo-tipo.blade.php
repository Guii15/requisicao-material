@props(['tipo'])
@php($teste = $tipo === \App\Enums\TipoRequisicao::TESTE)
{{-- Teste volta ao estoque (cinza suave). Uso e consumo sai e não volta (preto cheio). --}}
<span {{ $attributes->class(['selo', 'selo-neutro' => $teste, 'selo-forte' => ! $teste]) }}>{{ $tipo->rotulo() }}</span>
