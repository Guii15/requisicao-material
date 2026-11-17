<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fila de separação: diferente da fila de aprovação, aqui não tem setor nem dono. Qualquer
 * um do Estoque vê e separa qualquer requisição aprovada, de qualquer setor solicitante —
 * é por isso que "para" recebe o usuário mas não filtra por ele (a Policy já garante
 * is_estoque antes de chegar aqui; o parâmetro fica pela simetria com FilaDeAprovacao).
 */
class FilaDeSeparacao
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        // O Estoque vê o que é para o Estoque; outro setor vê o que foi pedido a ele (17/11/2026).
        return app(QuemAtende::class)->filtrar(Requisicao::query()->whereIn('status', StatusRequisicao::prontosParaSeparar()), $user);
    }
}
