@props(['status'])
@php
    // Cor por grupo: aguardando (âmbar), em andamento (azul), concluída (verde), reprovada ou com pendência
    // (vermelho). Cancelada e as demais encerradas ficam em cinza.
    $cancelada = $status === \App\Enums\StatusRequisicao::CANCELADA;
    $classe = match ($status->grupo()) {
        'aguardando' => 'selo-pendente',
        'andamento' => 'selo-info',
        'concluida' => 'selo-ok',
        'encerrada' => $cancelada ? 'selo-neutro' : 'selo-erro',
        'alerta' => 'selo-erro',
        default => 'selo-neutro',
    };
@endphp
<span {{ $attributes->class(['selo', $classe]) }}>{{ $status->rotulo() }}</span>
