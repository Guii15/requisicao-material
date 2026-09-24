<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila de baixa (só Uso e Consumo): a responsável pela baixa, nunca o próprio pedido dela.
 * Se ela mesma abriu o pedido, ele foge pra fila do Admin (decisão de 24/09/2026).
 */
class FilaDeBaixa
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        $query = Requisicao::query()
            ->where('status', StatusRequisicao::AGUARDANDO_BAIXA)
            ->where('solicitante_id', '!=', $user->id);

        if ($user->is_admin && ! $user->is_responsavel_baixa) {
            return $query->whereHas('solicitante', fn (Builder $q) => $q->where('is_responsavel_baixa', true));
        }

        return $query;
    }
}
