@props(['status', 'tipo'])
@php
    $etapas = \App\Support\Trilha::etapas($status, $tipo);
    $atual = collect($etapas)->first(fn ($e) => in_array($e['estado'], ['atual', 'falha'], true));
@endphp
{{-- Barra de progresso da requisição: uma fatia por etapa, com o nome da etapa em curso embaixo. --}}
<div {{ $attributes }} role="img" aria-label="{{ $status->rotulo() }}">
    <div class="flex gap-1">
        @foreach ($etapas as $etapa)
            <span class="trilha-seg" data-estado="{{ $etapa['estado'] }}" title="{{ $etapa['nome'] }}"></span>
        @endforeach
    </div>
    <p class="etiqueta mt-1.5 truncate {{ $atual && $atual['estado'] === 'falha' ? 'text-destrutivo' : '' }}">{{ $status->rotulo() }}</p>
</div>
