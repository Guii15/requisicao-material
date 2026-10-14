<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila de pedidos de compra: já aprovados pelos aprovadores de compras, esperando a decisão da
 * responsável pela baixa (inclusive os que ela mesma pediu).
 */
class FilaDeCompras
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()
            ->where('tipo', TipoRequisicao::COMPRA_FUNCIONARIO->value)
            ->where('status', StatusRequisicao::AGUARDANDO_COMPRA->value);
    }
}
