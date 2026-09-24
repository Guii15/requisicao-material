@props(['status'])
@php
    // Quadrado de cor + texto: a cor ajuda a bater o olho, o texto é que informa.
    // Reprovada é notícia ruim para quem pediu (vermelho); cancelada só encerra (cinza).
    $cor = match ($status->grupo()) {
        'aguardando' => 'bg-amber-500',
        'concluida' => 'bg-emerald-600',
        'encerrada' => $status === \App\Enums\StatusRequisicao::CANCELADA ? 'bg-slate-400' : 'bg-red-600',
        'alerta' => 'bg-red-600',
        default => 'bg-blue-700',
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-2 whitespace-nowrap']) }}>
    <span class="size-2 shrink-0 {{ $cor }}" aria-hidden="true"></span>
    {{ $status->rotulo() }}
</span>
