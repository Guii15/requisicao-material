<?php

namespace App\Services;

use App\Enums\EtapaAssinatura;
use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tudo o que a tela de detalhe e o PDF mostram da requisição, montado num lugar só
 * para que o papel impresso nunca diga algo diferente da tela.
 */
class FichaDaRequisicao
{
    public function __construct(
        private readonly AssinaturaService $assinaturas,
        private readonly FilaDeAprovacao $fila,
    ) {}

    /**
     * @return array{
     *     requisicao: Requisicao,
     *     verificacao: ResultadoVerificacao,
     *     pendentes: list<EtapaAssinatura>,
     *     aguardandoAprovacaoDe: Collection<int, User>,
     *     motivoSemAprovador: ?string,
     * }
     */
    public function dados(Requisicao $requisicao): array
    {
        $requisicao->loadMissing(['itens', 'assinaturas', 'eventos.usuario', 'solicitante', 'setor', 'reprovadoPor', 'canceladoPor']);

        $assinadas = $requisicao->assinaturas->pluck('etapa');
        $pendentes = $requisicao->status->isFinal()
            ? []
            : array_values(array_filter(
                EtapaAssinatura::fluxo($requisicao->tipo),
                fn (EtapaAssinatura $etapa) => ! $assinadas->contains($etapa),
            ));

        return [
            'requisicao' => $requisicao,
            'verificacao' => $this->assinaturas->verificar($requisicao),
            'pendentes' => $pendentes,
            'aguardandoAprovacaoDe' => $requisicao->status === StatusRequisicao::AGUARDANDO_APROVACAO
                ? $this->fila->aprovadoresElegiveis($requisicao)
                : new Collection,
            'motivoSemAprovador' => $this->fila->motivoSemAprovador($requisicao),
        ];
    }

    /**
     * Mesmos dados, mais quem imprimiu e quando: a cópia em papel sempre diz de quando é.
     *
     * @return array<string, mixed>
     */
    public function paraImpressao(Requisicao $requisicao, User $emissor): array
    {
        return $this->dados($requisicao) + ['emitidoPor' => $emissor, 'emitidoEm' => now()];
    }

    /**
     * PDF em A4, com "Página X de Y" no canto do rodapé.
     */
    public function pdf(Requisicao $requisicao, User $emissor): DomPdf
    {
        $pdf = Pdf::loadView('requisicoes.pdf', $this->paraImpressao($requisicao, $emissor))
            ->setPaper('a4')
            ->setOption('isFontSubsettingEnabled', true);

        // O total de páginas só existe depois de montar o documento.
        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_script(function (int $pagina, int $total, Canvas $canvas, FontMetrics $metricas) {
            $texto = "Página {$pagina} de {$total}";
            $fonte = $metricas->getFont('Helvetica');
            $margemDireita = 34.02; // 12 mm, a mesma do @page da view

            $canvas->text($canvas->get_width() - $margemDireita - $metricas->getTextWidth($texto, $fonte, 7), $canvas->get_height() - 51.5, $texto, $fonte, 7, [0.27, 0.27, 0.27]);
        });

        return $pdf;
    }
}
