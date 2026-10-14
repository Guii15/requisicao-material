<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * As requisições que a pessoa pode abrir, em forma de consulta (para listas como o painel).
 * É a RequisicaoPolicy::view traduzida para SQL: se uma mudar, a outra tem que mudar junto
 * (o RequisicoesVisiveisTest compara as duas).
 */
class RequisicoesVisiveis
{
    public function __construct(private readonly FilaDeAprovacao $fila) {}

    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        $query = Requisicao::query();

        if ($user->is_admin) {
            return $query;
        }

        $compra = TipoRequisicao::COMPRA_FUNCIONARIO->value;

        return $query->where(function (Builder $visivel) use ($user, $compra) {
            $visivel->where('requisicoes.solicitante_id', $user->id)
                ->orWhereIn('requisicoes.setor_id', DB::table('setor_aprovadores')->select('setor_id')->where('user_id', $user->id))
                ->orWhereExists(fn ($sub) => $sub->from('requisicao_assinaturas')
                    ->whereColumn('requisicao_assinaturas.requisicao_id', 'requisicoes.id')
                    ->where('requisicao_assinaturas.user_id', $user->id));

            // Quem pode decidir precisa ver (só quem está ativo decide).
            if ($user->ativo) {
                $visivel->orWhereIn('requisicoes.id', $this->fila->para($user)->select('requisicoes.id'));
            }

            if ($user->is_responsavel_baixa) {
                $visivel->orWhere(fn (Builder $q) => $q->where('requisicoes.tipo', $compra)
                    ->whereIn('requisicoes.status', [StatusRequisicao::AGUARDANDO_COMPRA, StatusRequisicao::COMPRA_APROVADA, StatusRequisicao::COMPRA_REPROVADA]));

                $visivel->orWhere(fn (Builder $q) => $q->where('requisicoes.tipo', TipoRequisicao::USO_CONSUMO->value)
                    ->whereIn('requisicoes.status', [StatusRequisicao::AGUARDANDO_BAIXA, StatusRequisicao::BAIXADA]));
            }

            // O estoque nunca vê compra de funcionário nem o que não foi aprovado.
            if ($user->is_estoque || $user->is_lider_estoque) {
                $visivel->orWhere(fn (Builder $q) => $q->where('requisicoes.tipo', '!=', $compra)
                    ->whereIn('requisicoes.status', array_filter(StatusRequisicao::cases(), fn (StatusRequisicao $s) => $s->visivelParaEstoque())));
            }
        });
    }
}
