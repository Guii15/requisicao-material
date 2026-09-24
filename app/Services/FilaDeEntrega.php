<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila de entrega: qualquer um do estoque, de qualquer setor solicitante (mesma lógica
 * compartilhada da FilaDeSeparacao — ver o comentário lá).
 */
class FilaDeEntrega
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()->where('status', StatusRequisicao::PRONTA_PARA_RETIRADA);
    }
}
