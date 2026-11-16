<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Transferências de filial já enviadas, esperando quem pediu (se não foi quem enviou)
 * confirmar que o produto chegou.
 */
class FilaDeRecebimento
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()
            ->where('tipo', TipoRequisicao::TRANSFERENCIA_FILIAL->value)
            ->where('status', StatusRequisicao::AGUARDANDO_RECEBIMENTO->value)
            ->where('solicitante_id', $user->id)
            // Quem enviou não confirma o próprio envio.
            ->where(fn (Builder $q) => $q->whereNull('enviado_por_id')->orWhere('enviado_por_id', '!=', $user->id));
    }
}
