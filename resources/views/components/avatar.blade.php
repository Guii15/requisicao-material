@props(['nome'])
@php
    $iniciais = collect(preg_split('/\s+/', trim((string) $nome)))
        ->filter()
        ->map(fn (string $parte) => mb_strtoupper(mb_substr($parte, 0, 1)))
        ->take(2)
        ->implode('');
@endphp
{{-- Círculo com as iniciais do nome. O tamanho e a cor podem ser trocados por classe. --}}
<span {{ $attributes->class(['avatar']) }} aria-hidden="true">{{ $iniciais }}</span>
