<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila de liberação (só Uso e Consumo): todo líder do estoque vê, inclusive quem aprovou ou
 * separou a requisição (mesma regra da RequisicaoPolicy::liberar: sem trava de quem já agiu).
 */
class FilaDeLiberacao
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()
            ->where('status', StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE);
    }
}
