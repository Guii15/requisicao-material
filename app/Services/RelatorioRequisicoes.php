<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\RequisicaoItem;
use App\Support\Texto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Relatórios pedidos pela Meire: Teste, Uso e Consumo e Pedidos de compra de funcionários.
 * Uma linha por item, filtrada por período (data do pedido), setor e situação.
 */
class RelatorioRequisicoes
{
    /** Tipos com relatório, na ordem das abas. */
    public const TIPOS = [TipoRequisicao::USO_CONSUMO, TipoRequisicao::COMPRA_FUNCIONARIO, TipoRequisicao::TESTE];

    /**
     * @param  array{tipo: TipoRequisicao, de: Carbon, ate: Carbon, setor_id: ?int, status: ?StatusRequisicao}  $filtro
     * @return Builder<RequisicaoItem>
     */
    public function consulta(array $filtro): Builder
    {
        return RequisicaoItem::query()
            ->select('requisicao_itens.*')
            ->join('requisicoes', 'requisicoes.id', '=', 'requisicao_itens.requisicao_id')
            ->where('requisicoes.tipo', $filtro['tipo']->value)
            ->whereBetween('requisicoes.created_at', [$filtro['de']->copy()->startOfDay(), $filtro['ate']->copy()->endOfDay()])
            ->when($filtro['setor_id'], fn (Builder $q, int $setor) => $q->where('requisicoes.setor_id', $setor))
            ->when($filtro['status'], fn (Builder $q, StatusRequisicao $status) => $q->where('requisicoes.status', $status->value))
            ->with([
                'requisicao.solicitante', 'requisicao.setor', 'requisicao.aprovadoPor', 'requisicao.separadoPor',
                'requisicao.baixaPor', 'requisicao.compraDecididaPor',
            ])
            ->orderByDesc('requisicoes.created_at')
            ->orderBy('requisicao_itens.id');
    }

    /**
     * Colunas próprias de cada tipo (além das comuns).
     *
     * @return array<string, string> chave => título
     */
    public function colunas(TipoRequisicao $tipo): array
    {
        $comuns = [
            'numero' => 'Nº',
            'data' => 'Data do pedido',
            'solicitante' => 'Solicitante',
            'setor' => 'Setor',
            'codigo' => 'Código',
            'produto' => 'Produto',
            'unidade' => 'Unid.',
            'qtd' => 'Qtd. pedida',
        ];

        $proprias = match ($tipo) {
            TipoRequisicao::USO_CONSUMO => [
                'qtd_separada' => 'Qtd. separada',
                'aprovado_por' => 'Aprovado por',
                'separado_por' => 'Separado por',
                'baixa_por' => 'Baixa por',
                'baixa_em' => 'Data da baixa',
            ],
            TipoRequisicao::COMPRA_FUNCIONARIO => [
                'pagamento' => 'Pagamento',
                'aprovado_por' => 'Aprovado por',
                'compra_por' => 'Compra decidida por',
                'compra_em' => 'Data da decisão',
            ],
            default => [
                'qtd_separada' => 'Qtd. separada',
                'devolucao_prevista' => 'Devolução prevista',
                'devolvido_ok' => 'Devolvido ok',
                'devolvido_defeito' => 'Com defeito',
                'nao_devolvido' => 'Não devolvido',
            ],
        };

        return $comuns + $proprias + ['situacao' => 'Situação'];
    }

    /**
     * @return array<string, string>
     */
    public function linha(RequisicaoItem $item): array
    {
        $r = $item->requisicao;
        $data = fn (?Carbon $d, string $formato = 'd/m/Y H:i') => $d?->format($formato) ?? '';
        $qtd = fn ($valor) => $valor === null ? '' : rtrim(rtrim(number_format((float) $valor, 3, ',', '.'), '0'), ',');

        return [
            'numero' => $r->numero,
            'data' => $data($r->created_at),
            'solicitante' => $r->solicitante?->nome ?? '',
            'setor' => $r->setor ? Texto::setor($r->setor->nome) : '',
            'codigo' => (string) $item->codigo,
            'produto' => $item->descricao,
            'unidade' => $item->unidade,
            'qtd' => $qtd($item->qtd_solicitada),
            'qtd_separada' => $qtd($item->qtd_separada),
            'aprovado_por' => $r->aprovadoPor?->nome ?? '',
            'separado_por' => $r->separadoPor?->nome ?? '',
            'baixa_por' => $r->baixaPor?->nome ?? '',
            'baixa_em' => $data($r->baixa_em),
            'pagamento' => match (true) {
                $r->forma_pagamento === null => '',
                $r->parcelas > 1 => $r->forma_pagamento->rotulo().' ('.$r->parcelas.'x)',
                default => $r->forma_pagamento->rotulo(),
            },
            'compra_por' => $r->compraDecididaPor?->nome ?? '',
            'compra_em' => $data($r->compra_decidido_em),
            'devolucao_prevista' => $data($r->data_prevista_devolucao, 'd/m/Y'),
            'devolvido_ok' => $qtd($item->qtd_devolvida_ok),
            'devolvido_defeito' => $qtd($item->qtd_devolvida_defeito),
            'nao_devolvido' => $qtd($item->qtd_nao_devolvida),
            'situacao' => $item->isRecusado() ? 'Item recusado' : $r->status->rotulo($r->tipo),
        ];
    }

    /**
     * Status que fazem sentido no filtro de cada tipo.
     *
     * @return list<StatusRequisicao>
     */
    public function statusDoTipo(TipoRequisicao $tipo): array
    {
        $vistos = [];
        $fila = [StatusRequisicao::AGUARDANDO_APROVACAO];
        while ($fila !== []) {
            $status = array_shift($fila);
            if (isset($vistos[$status->value])) {
                continue;
            }
            $vistos[$status->value] = $status;
            foreach ($status->destinos($tipo) as $destino) {
                $fila[] = $destino;
            }
        }

        // Os de passagem automática nunca ficam parados: não adianta filtrar por eles.
        return array_values(array_filter($vistos, fn (StatusRequisicao $s) => $s->proximoAutomatico($tipo) === null));
    }
}
