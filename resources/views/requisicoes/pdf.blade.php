@php
    use App\Enums\EtapaAssinatura;
    use App\Enums\StatusRequisicao;
    use App\Enums\TipoRequisicao;
    use App\Services\AssinaturaService;
    use App\Support\Quantidade;

    $dataHora = fn ($data) => $data->format('d/m/Y').' às '.$data->format('H:i');
    $cor = match ($requisicao->status->grupo()) {
        'aguardando' => '#7c4a03',
        'concluida' => '#14532d',
        'encerrada', 'alerta' => '#7f1d1d',
        default => '#000069',
    };
    // Cancelada ou reprovada: sai com a marca por cima, para não servir de autorização.
    $marca = match (true) {
        ! $verificacao->integro => 'DIVERGENTE',
        $requisicao->status === StatusRequisicao::CANCELADA => 'CANCELADA',
        in_array($requisicao->status, [StatusRequisicao::REPROVADA, StatusRequisicao::REPROVADA_ESTOQUE], true) => 'REPROVADA',
        default => null,
    };
    $blocos = $requisicao->assinaturas
        ->map(fn ($assinatura) => ['etapa' => $assinatura->etapa, 'assinatura' => $assinatura])
        ->concat(collect($pendentes)->map(fn ($etapa) => ['etapa' => $etapa, 'assinatura' => null]))
        ->values()
        ->chunk(2);
    $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('imagens/logo.png')));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $requisicao->numero }}</title>
    <style>
        @page { margin: 12mm 12mm 22mm 12mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9pt; color: #111; line-height: 1.3; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; text-align: left; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; }
        .rotulo { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3pt; color: #555; }
        .valor { font-size: 9.5pt; margin-top: 1.5pt; }
        .pequeno { font-size: 7.5pt; color: #444; margin-top: 1.5pt; }
        .pendente { color: #666; }
        .grade { border: 0.8pt solid #222; }
        .grade td, .grade th { border: 0.5pt solid #8a8a8a; padding: 3pt 5pt 4pt; }
        .secao { margin-top: 8pt; border: 0.8pt solid #222; border-bottom: 0; background: #e4e4e7; padding: 2.5pt 5pt; font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4pt; }
        .itens th { background: #f4f4f5; font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.3pt; color: #444; }
        .itens tr { page-break-inside: avoid; }
        .num { text-align: right; }
        .divergente { margin-top: 6pt; border: 1.2pt solid #7f1d1d; padding: 5pt 7pt; color: #7f1d1d; }
        #marca { position: fixed; z-index: -1; top: 105mm; left: 0; right: 0; text-align: center; font-size: 76pt; font-weight: bold; color: #dcdce0; transform: rotate(-30deg); }
        /* O número da página ("Página 1 de 2") é escrito pelo controller, à direita, depois de montar o PDF. */
        #rodape { position: fixed; bottom: -16mm; left: 0; right: 0; height: 12mm; border-top: 0.5pt solid #999; padding: 3pt 30mm 0 0; font-size: 7pt; color: #444; }
    </style>
</head>
<body>
    @if ($marca)
        <div id="marca">{{ $marca }}</div>
    @endif

    <div id="rodape">
        Emitido em {{ $dataHora($emitidoEm) }} por {{ $emitidoPor->nome }}.
        Cópia para conferência: a situação válida é a que está no sistema
        (Requisição de Material, número {{ $requisicao->numero }}).
    </div>

    <table>
        <tr>
            <td style="width: 46mm; background: #000069; padding: 3.5mm 4mm;">
                <img src="{{ $logo }}" alt="Binário Tecnologia" style="width: 38mm;">
            </td>
            <td style="padding: 1.5mm 0 0 5mm;">
                <div style="font-size: 13pt; font-weight: bold;">Requisição de Material</div>
                <div class="pequeno">Retirada de material do estoque</div>
            </td>
            <td style="width: 62mm; text-align: right; padding-top: 1mm;">
                <div class="rotulo">Número</div>
                <div class="mono" style="font-size: 15pt; font-weight: bold;">{{ $requisicao->numero }}</div>
            </td>
        </tr>
    </table>

    <table class="grade" style="margin-top: 5mm;">
        <tr>
            <td style="width: 40%;">
                <div class="rotulo">Situação</div>
                <div style="font-size: 12pt; font-weight: bold; color: {{ $cor }}; margin-top: 1pt;">{{ mb_strtoupper($requisicao->status->rotulo()) }}</div>
                <div class="pequeno">desde {{ $dataHora($requisicao->status_alterado_em) }}</div>
            </td>
            <td style="width: 32%;">
                <div class="rotulo">Tipo</div>
                <div class="valor"><strong>{{ $requisicao->tipo->rotulo() }}</strong></div>
                <div class="pequeno">
                    @if ($requisicao->tipo === TipoRequisicao::TESTE)
                        Devolução prevista: {{ $requisicao->data_prevista_devolucao?->format('d/m/Y') }}
                    @elseif ($requisicao->tipo === TipoRequisicao::COMPRA_FUNCIONARIO)
                        Compra para funcionário, sem passar pelo estoque
                    @else
                        Material consumido, com baixa no WinThor
                    @endif
                </div>
            </td>
            <td>
                <div class="rotulo">Aberta em</div>
                <div class="valor">{{ $dataHora($requisicao->created_at) }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="rotulo">Solicitante</div>
                <div class="valor">{{ $requisicao->solicitante->nome }}</div>
            </td>
            <td>
                <div class="rotulo">Setor</div>
                <div class="valor">{{ $requisicao->setor->nome }}</div>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <div class="rotulo">Finalidade</div>
                <div class="valor">{!! nl2br(e($requisicao->finalidade)) !!}</div>
            </td>
        </tr>
        @if (filled($requisicao->justificativa))
            <tr>
                <td colspan="3">
                    <div class="rotulo">Justificativa</div>
                    <div class="valor">{!! nl2br(e($requisicao->justificativa)) !!}</div>
                </td>
            </tr>
        @endif
        @if ($requisicao->retirado_por_nome || $requisicao->devolvido_por_nome)
            <tr>
                <td colspan="3">
                    @if ($requisicao->retirado_por_nome)
                        <div class="rotulo">Retirado por</div>
                        <div class="valor">{{ $requisicao->retirado_por_nome }}</div>
                    @endif
                    @if ($requisicao->devolvido_por_nome)
                        <div class="rotulo">Devolvido por</div>
                        <div class="valor">{{ $requisicao->devolvido_por_nome }}</div>
                    @endif
                </td>
            </tr>
        @endif
        @if ($requisicao->motivo_reprovacao)
            <tr>
                <td colspan="3">
                    <div class="rotulo">Motivo da reprovação</div>
                    <div class="valor">{!! nl2br(e($requisicao->motivo_reprovacao)) !!}</div>
                    <div class="pequeno">{{ $requisicao->reprovadoPor?->nome }}, {{ $dataHora($requisicao->reprovado_em) }}</div>
                </td>
            </tr>
        @endif
        @if ($requisicao->motivo_cancelamento)
            <tr>
                <td colspan="3">
                    <div class="rotulo">Motivo do cancelamento</div>
                    <div class="valor">{!! nl2br(e($requisicao->motivo_cancelamento)) !!}</div>
                    <div class="pequeno">{{ $requisicao->canceladoPor?->nome }}, {{ $dataHora($requisicao->cancelado_em) }}</div>
                </td>
            </tr>
        @endif
    </table>

    <div class="secao">Itens ({{ $requisicao->itens->count() }})</div>
    <table class="grade itens">
        <thead>
            <tr>
                <th class="num" style="width: 7mm;">#</th>
                <th style="width: 20mm;">Cód.</th>
                <th>Descrição</th>
                <th style="width: 14mm;">Un.</th>
                <th class="num" style="width: 27mm;">Qtd. solicitada</th>
                <th class="num" style="width: 27mm;">Qtd. separada</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($requisicao->itens as $item)
                <tr>
                    <td class="num">{{ $loop->iteration }}</td>
                    <td>{{ $item->codigo }}</td>
                    <td>{{ $item->descricao }}</td>
                    <td>{{ $item->unidade }}</td>
                    <td class="num">{{ Quantidade::formatar($item->qtd_solicitada) }}</td>
                    <td class="num">{{ Quantidade::formatar($item->qtd_separada) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="secao">Assinaturas</div>
    <table class="grade">
        @foreach ($blocos as $par)
            <tr>
                @foreach ($par as $bloco)
                    <td style="width: 50%;">
                        <div class="rotulo">{{ $bloco['etapa']->rotulo() }}</div>
                        @if ($bloco['assinatura'])
                            <div class="valor">
                                <strong>{{ $bloco['assinatura']->nome_assinante }}</strong>@if ($bloco['assinatura']->cargo_assinante), {{ $bloco['assinatura']->cargo_assinante }}@endif
                            </div>
                            <div class="pequeno">
                                {{ $dataHora($bloco['assinatura']->assinado_em) }}.
                                Código <span class="mono">{{ AssinaturaService::codigoCurto($bloco['assinatura']->hash_documento) }}</span>
                            </div>
                        @elseif ($bloco['etapa'] === EtapaAssinatura::APROVACAO_SETOR && $requisicao->status === StatusRequisicao::AGUARDANDO_APROVACAO)
                            <div class="valor pendente">
                                @if ($aguardandoAprovacaoDe->isEmpty())
                                    Nenhum aprovador disponível. Procure a TI.
                                @else
                                    Aguardando {{ $aguardandoAprovacaoDe->pluck('nome')->join(', ', ' ou ') }}
                                @endif
                            </div>
                            @if ($motivoSemAprovador)
                                <div class="pequeno">Aprovação pelos líderes do estoque: {{ mb_strtolower($motivoSemAprovador) }}.</div>
                            @endif
                        @else
                            <div class="valor pendente">Pendente</div>
                        @endif
                    </td>
                @endforeach
                @if ($par->count() === 1)
                    <td style="width: 50%;"></td>
                @endif
            </tr>
        @endforeach
    </table>

    @if ($verificacao->integro)
        <p class="pequeno" style="margin-top: 4pt;">
            Assinaturas íntegras: o conteúdo desta requisição confere com o que foi assinado em cada etapa.
        </p>
    @else
        <div class="divergente">
            <strong>DOCUMENTO DIVERGENTE.</strong> O conteúdo não confere com as assinaturas. Não use este documento e avise a TI.
            @foreach ($verificacao->problemas as $problema)
                <div class="pequeno" style="color: #7f1d1d;">{{ $problema }}</div>
            @endforeach
        </div>
    @endif
</body>
</html>
