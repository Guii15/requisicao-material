<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Transferências de filial já aprovadas pelo setor, esperando quem faz a transferência no WinThor.
 */
class FilaDeTransferencia
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()
            ->where('tipo', TipoRequisicao::TRANSFERENCIA_FILIAL->value)
            ->where('status', StatusRequisicao::AGUARDANDO_TRANSFERENCIA->value);
    }
}
