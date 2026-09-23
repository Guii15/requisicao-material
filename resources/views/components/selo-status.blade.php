@props(['status'])
@php
    [$classes, $icone] = match ($status->grupo()) {
        'aguardando' => ['border-slate-300 bg-white text-slate-700', 'phosphor-hourglass-medium'],
        'concluida' => ['border-emerald-300 bg-emerald-50 text-emerald-900', 'phosphor-check-bold'],
        'encerrada' => ['border-slate-300 bg-slate-100 text-slate-600', 'phosphor-prohibit'],
        'alerta' => ['border-red-300 bg-red-50 text-red-800', 'phosphor-warning-bold'],
        default => ['border-slate-300 bg-slate-100 text-slate-800', 'phosphor-arrow-right'],
    };
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-sm border px-1.5 py-0.5 text-xs leading-4 font-medium whitespace-nowrap', $classes]) }}>
    <x-dynamic-component :component="$icone" class="size-3.5" aria-hidden="true" />
    {{ $status->rotulo() }}
</span>
