<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila de liberação (só Uso e Consumo): qualquer líder do estoque, menos quem aprovou ou
 * separou a própria requisição (antifraude — ver RequisicaoPolicy::liberar).
 */
class FilaDeLiberacao
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()
            ->where('status', StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE)
            ->where(fn (Builder $q) => $q->whereNull('aprovado_por_id')->orWhere('aprovado_por_id', '!=', $user->id))
            ->where(fn (Builder $q) => $q->whereNull('separado_por_id')->orWhere('separado_por_id', '!=', $user->id));
    }
}
