<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * As requisições que o usuário pode abrir, como filtro de consulta. É a mesma regra da
 * RequisicaoPolicy::view, escrita em SQL para listas paginadas (ex.: atividade do painel):
 * filtrar linha a linha pela Policy quebraria a paginação.
 *
 * Mudou a Policy, muda aqui também: o teste RequisicoesVisiveisTest compara as duas.
 */
class RequisicoesVisiveis
{
    public function __construct(private readonly FilaDeAprovacao $fila) {}

    /**
     * @param  Builder<Requisicao>  $query
     */
    public function aplicar(Builder $query, User $user): void
    {
        if ($user->is_admin) {
            return;
        }

        $query->where(function (Builder $visivel) use ($user) {
            $visivel->where('requisicoes.solicitante_id', $user->id)
                ->orWhereIn('requisicoes.setor_id', DB::table('setor_aprovadores')->select('setor_id')->where('user_id', $user->id))
                ->orWhereIn('requisicoes.id', $this->fila->para($user)->select('requisicoes.id'))
                ->orWhereExists(fn (QueryBuilder $sub) => $sub->selectRaw('1')
                    ->from('requisicao_assinaturas')
                    ->whereColumn('requisicao_assinaturas.requisicao_id', 'requisicoes.id')
                    ->where('requisicao_assinaturas.user_id', $user->id));

            // Compra de funcionário não passa pelo estoque: só a responsável pela baixa, a partir da decisão.
            if ($user->is_responsavel_baixa) {
                $visivel->orWhere(fn (Builder $q) => $q
                    ->where('requisicoes.tipo', TipoRequisicao::COMPRA_FUNCIONARIO->value)
                    ->whereIn('requisicoes.status', $this->valores([StatusRequisicao::AGUARDANDO_COMPRA, StatusRequisicao::COMPRA_APROVADA, StatusRequisicao::COMPRA_REPROVADA])));

                $visivel->orWhere(fn (Builder $q) => $q
                    ->where('requisicoes.tipo', TipoRequisicao::USO_CONSUMO->value)
                    ->whereIn('requisicoes.status', $this->valores([StatusRequisicao::AGUARDANDO_BAIXA, StatusRequisicao::BAIXADA])));
            }

            if ($user->is_estoque || $user->is_lider_estoque) {
                $visivel->orWhere(fn (Builder $q) => $q
                    ->where('requisicoes.tipo', '!=', TipoRequisicao::COMPRA_FUNCIONARIO->value)
                    ->whereNotIn('requisicoes.status', $this->valores(StatusRequisicao::ocultosDoEstoque())));
            }
        });
    }

    /**
     * @param  list<StatusRequisicao>  $status
     * @return list<string>
     */
    private function valores(array $status): array
    {
        return array_map(fn (StatusRequisicao $s) => $s->value, $status);
    }
}
