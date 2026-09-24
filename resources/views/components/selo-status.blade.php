@props(['status'])
@php
    // Barra lateral de cor (sem fundo preenchido) + texto: a cor só marca, o texto informa.
    // Reprovada é notícia ruim para quem pediu (vermelho); cancelada só encerra (cinza).
    $cor = match ($status->grupo()) {
        'aguardando' => 'bg-amber-500',
        'concluida' => 'bg-emerald-600',
        'encerrada' => $status === \App\Enums\StatusRequisicao::CANCELADA ? 'bg-slate-400' : 'bg-red-600',
        'alerta' => 'bg-red-600',
        default => 'bg-blue-700',
    };
    // Pulso só em "aguardando": é o único grupo que representa algo esperando acontecer agora.
    $aguardando = $status->grupo() === 'aguardando';
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-2 whitespace-nowrap']) }}>
    <span class="h-3.5 w-1 shrink-0 {{ $cor }} {{ $aguardando ? 'pulso-suave' : '' }}" aria-hidden="true"></span>
    {{ $status->rotulo() }}
</span>
