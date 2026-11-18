<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Transferências de filial já feitas no WinThor, esperando o estoque enviar o produto (com a foto da saída).
 */
class FilaDeEnvio
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()
            ->where('tipo', TipoRequisicao::TRANSFERENCIA_FILIAL->value)
            ->where(fn (Builder $q) => $q->where('status', StatusRequisicao::AGUARDANDO_ENVIO->value)
                // Aprovada e ainda não enviada: o estoque pode enviar antes da transferência no WinThor (18/11/2026).
                ->orWhere(fn (Builder $a) => $a->where('status', StatusRequisicao::AGUARDANDO_TRANSFERENCIA->value)->whereNull('enviado_por_id')));
    }
}
