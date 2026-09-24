<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Quem aprova o quê.
 *
 * Todos os aprovadores do setor (líder e sublíder valem igual) veem a fila; ninguém vê o
 * próprio pedido. Quando o setor não tem outro aprovador ativo além do solicitante, a
 * requisição cai para os líderes do estoque (decisão de 24/09/2026: o Admin fica fora
 * da aprovação por enquanto).
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

                if ($user->is_lider_estoque) {
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
     * Quem pode aprovar a requisição agora, em ordem alfabética: os aprovadores ativos do
     * setor ou, se não houver, os líderes do estoque ativos. Nunca o próprio solicitante.
     *
     * @return Collection<int, User>
     */
    public function aprovadoresElegiveis(Requisicao $requisicao): Collection
    {
        $query = User::query()
            ->where('ativo', true)
            ->whereKeyNot($requisicao->solicitante_id)
            ->orderBy('nome');

        return $this->semAprovadorElegivel($requisicao)
            ? $query->where('is_lider_estoque', true)->get()
            : $query->whereIn('id', DB::table('setor_aprovadores')->select('user_id')->where('setor_id', $requisicao->setor_id))->get();
    }

    /**
     * Por que a requisição foi para os líderes do estoque (nulo quando o setor aprova).
     */
    public function motivoSemAprovador(Requisicao $requisicao): ?string
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
