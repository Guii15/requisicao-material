@props(['status'])
@php
    // Sem cor para chamar atenção: aguardando é contorno, concluída é cinza suave, encerrada por reprovação
    // ou alerta é vermelho (o único aviso do sistema), cancelada só se apaga.
    $cancelada = $status === \App\Enums\StatusRequisicao::CANCELADA;
    $classe = match ($status->grupo()) {
        'aguardando' => 'selo-contorno',
        'concluida' => 'selo-ok',
        'encerrada' => $cancelada ? 'selo-neutro text-mid-gray' : 'selo-erro',
        'alerta' => 'selo-erro',
        default => 'selo-neutro',
    };
@endphp
<span {{ $attributes->class(['selo', $classe]) }}>{{ $status->rotulo() }}</span>
