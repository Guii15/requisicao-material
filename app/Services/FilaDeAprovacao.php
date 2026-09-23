<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Quem aprova o quê.
 *
 * Todos os aprovadores do setor (líder e sublíder valem igual) veem a fila; ninguém vê o
 * próprio pedido. Quando o setor não tem outro aprovador ativo além do solicitante, a
 * requisição cai na fila do Admin.
 */
class FilaDeAprovacao
{
    /**
     * @return Builder<Requisicao>
     */
    public function para(User $user): Builder
    {
        return Requisicao::query()
            ->where('status', StatusRequisicao::AGUARDANDO_APROVACAO)
            ->where('solicitante_id', '!=', $user->id)
            ->where(function (Builder $query) use ($user) {
                $query->whereIn('setor_id', DB::table('setor_aprovadores')->select('setor_id')->where('user_id', $user->id));

                if ($user->is_admin) {
                    $query->orWhereNotExists(fn (QueryBuilder $sub) => $this->aprovadorElegivel($sub)
                        ->whereColumn('setor_aprovadores.setor_id', 'requisicoes.setor_id')
                        ->whereColumn('setor_aprovadores.user_id', '!=', 'requisicoes.solicitante_id'));
                }
            });
    }

    public function semAprovadorElegivel(Requisicao $requisicao): bool
    {
        return ! $this->aprovadorElegivel(DB::query())
            ->where('setor_aprovadores.setor_id', $requisicao->setor_id)
            ->where('setor_aprovadores.user_id', '!=', $requisicao->solicitante_id)
            ->exists();
    }

    /**
     * Por que a requisição está na fila do Admin (nulo quando não está).
     */
    public function motivoFilaAdmin(Requisicao $requisicao): ?string
    {
        if ($requisicao->status !== StatusRequisicao::AGUARDANDO_APROVACAO || ! $this->semAprovadorElegivel($requisicao)) {
            return null;
        }

        $ativos = DB::table('setor_aprovadores')
            ->join('users', 'users.id', '=', 'setor_aprovadores.user_id')
            ->where('setor_aprovadores.setor_id', $requisicao->setor_id)
            ->where('users.ativo', true)
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id);

        if ($ativos->isEmpty()) {
            return DB::table('setor_aprovadores')->where('setor_id', $requisicao->setor_id)->exists()
                ? 'Nenhum aprovador ativo no setor'
                : 'Setor sem aprovador cadastrado';
        }

        return 'O solicitante é o único aprovador do setor';
    }

    private function aprovadorElegivel(QueryBuilder $query): QueryBuilder
    {
        return $query->selectRaw('1')
            ->from('setor_aprovadores')
            ->join('users as aprovador', 'aprovador.id', '=', 'setor_aprovadores.user_id')
            ->where('aprovador.ativo', true);
    }
}
