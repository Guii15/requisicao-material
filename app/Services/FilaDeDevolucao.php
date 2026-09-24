<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila de devolução (só Teste): o que está em posse de alguém, esperando voltar.
 */
class FilaDeDevolucao
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()
            ->where('status', StatusRequisicao::EM_POSSE)
            ->where('tipo', TipoRequisicao::TESTE);
    }
}
