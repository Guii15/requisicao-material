<?php

namespace App\Services;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
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
 * da aprovação por enquanto). Nos setores marcados com lideres_estoque_aprovam (Showroom e
 * Ninja Place, decisão de 23/10/2026) os líderes do estoque aprovam sempre, junto com o setor.
 * Transferência de filial é aprovada pelos líderes do estoque e pelos líderes do Showroom (16/11/2026).
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
            ->where(function (Builder $fila) use ($user) {
                // Uso e Consumo e Teste: direto para os líderes do estoque, sem o líder do setor (17/11/2026).
                $fila->whereRaw('1 = 0');

                if ($user->is_lider_estoque) {
                    $fila->orWhereNotIn('tipo', [TipoRequisicao::COMPRA_FUNCIONARIO->value, TipoRequisicao::TRANSFERENCIA_FILIAL->value]);
                }

                // Transferência de filial: líderes do estoque e líderes do Showroom (decisão de 16/11/2026).
                if ($this->aprovaTransferencia($user)) {
                    $fila->orWhere('tipo', TipoRequisicao::TRANSFERENCIA_FILIAL->value);
                }

                // Compra de funcionário: só quem aprova compras, de qualquer setor.
                if ($user->aprova_compras) {
                    $fila->orWhere('tipo', TipoRequisicao::COMPRA_FUNCIONARIO->value);
                }
            });
    }

    /**
     * Os líderes do estoque podem aprovar esta requisição (fora a compra de funcionário).
     */
    public function lideresDoEstoqueAprovam(Requisicao $requisicao): bool
    {
        if ($requisicao->tipo === TipoRequisicao::TRANSFERENCIA_FILIAL) {
            return true;
        }

        return (bool) $requisicao->setor()->value('lideres_estoque_aprovam') || $this->semAprovadorElegivel($requisicao);
    }

    /**
     * Transferência de filial: aprovam os líderes do estoque e os líderes (e sublíderes) do Showroom.
     */
    public function aprovaTransferencia(User $user): bool
    {
        return $user->is_lider_estoque
            || $user->setoresAprovados()->whereIn('setores.id', $this->setorShowroom())->exists();
    }

    private function setorShowroom(): QueryBuilder
    {
        return DB::table('setores')->select('id')->where('nome', 'SHOWROOM');
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
     * setor ou, se não houver, os líderes do estoque ativos (nos setores marcados, os dois
     * juntos). Nunca o próprio solicitante.
     *
     * @return Collection<int, User>
     */
    public function aprovadoresElegiveis(Requisicao $requisicao): Collection
    {
        $query = User::query()
            ->where('ativo', true)
            ->whereKeyNot($requisicao->solicitante_id)
            ->orderBy('nome');

        if ($requisicao->tipo === TipoRequisicao::COMPRA_FUNCIONARIO) {
            return $query->where('aprova_compras', true)->get();
        }

        if ($requisicao->tipo === TipoRequisicao::TRANSFERENCIA_FILIAL) {
            return $query->where(fn (Builder $q) => $q
                ->where('is_lider_estoque', true)
                ->orWhereIn('id', DB::table('setor_aprovadores')->select('user_id')->whereIn('setor_id', $this->setorShowroom())))
                ->get();
        }

        return $query->where('is_lider_estoque', true)->get();
    }

    /**
     * Antes dizia por que a requisição tinha ido para os líderes do estoque. Desde 17/11/2026
     * todo Uso e Consumo e Teste vai direto para eles, então não há mais esse aviso.
     */
    public function motivoSemAprovador(Requisicao $requisicao): ?string
    {
        return null;
    }

    private function aprovadorElegivel(QueryBuilder $query): QueryBuilder
    {
        return $query->selectRaw('1')
            ->from('setor_aprovadores')
            ->join('users as aprovador', 'aprovador.id', '=', 'setor_aprovadores.user_id')
            ->where('aprovador.ativo', true);
    }
}
