<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila de compras de funcionário: já aprovadas pelos aprovadores de compras, esperando a
 * decisão da responsável pela baixa. Nunca o próprio pedido dela; se ela mesma abriu, o
 * pedido vai para a fila do Admin (mesma regra da baixa).
 */
class FilaDeCompras
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        $query = Requisicao::query()
            ->where('tipo', TipoRequisicao::COMPRA_FUNCIONARIO->value)
            ->where('status', StatusRequisicao::AGUARDANDO_COMPRA->value)
            ->where('solicitante_id', '!=', $user->id);

        if ($user->is_admin && ! $user->is_responsavel_baixa) {
            return $query->whereHas('solicitante', fn (Builder $q) => $q->where('is_responsavel_baixa', true));
        }

        return $query;
    }
}
