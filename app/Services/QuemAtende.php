<?php

namespace App\Services;

use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quem atende o pedido (separa, entrega e confere a devolução): o Estoque, ou o setor
 * escolhido em "Pedido para o setor" quando não é o Estoque (decisão de 17/11/2026).
 */
class QuemAtende
{
    private ?int $estoque = null;

    public function idDoEstoque(): ?int
    {
        return $this->estoque ??= Setor::idDoEstoque();
    }

    public function paraOEstoque(Requisicao $requisicao): bool
    {
        return $requisicao->setor_destino_id === null || $requisicao->setor_destino_id === $this->idDoEstoque();
    }

    /**
     * Pedido de Uso e Consumo ou Teste para outro setor: nasce aprovado, sem assinatura de aprovação.
     */
    public function semAprovacao(Requisicao $requisicao): bool
    {
        return in_array($requisicao->tipo, [TipoRequisicao::USO_CONSUMO, TipoRequisicao::TESTE], true)
            && ! $this->paraOEstoque($requisicao)
            && $requisicao->aprovado_por_id === null;
    }

    public function atende(User $user, Requisicao $requisicao): bool
    {
        return $this->paraOEstoque($requisicao)
            ? (bool) $user->is_estoque
            : $user->setor_id !== null && $user->setor_id === $requisicao->setor_destino_id;
    }

    /**
     * Só os pedidos que o usuário atende.
     *
     * @param  Builder<Requisicao>  $query
     * @return Builder<Requisicao>
     */
    public function filtrar(Builder $query, User $user): Builder
    {
        $estoque = $this->idDoEstoque();

        return $query->where(function (Builder $q) use ($user, $estoque) {
            $q->whereRaw('1 = 0');

            if ($user->is_estoque) {
                $q->orWhereNull('requisicoes.setor_destino_id')->orWhere('requisicoes.setor_destino_id', $estoque);
            }

            if ($user->setor_id !== null && $user->setor_id !== $estoque) {
                $q->orWhere('requisicoes.setor_destino_id', $user->setor_id);
            }
        });
    }

    /**
     * O setor do usuário já recebeu algum pedido (para mostrar Separação/Devolução no menu).
     */
    public function setorRecebePedidos(User $user): bool
    {
        return $user->setor_id !== null
            && $user->setor_id !== $this->idDoEstoque()
            && Requisicao::query()->where('setor_destino_id', $user->setor_id)->exists();
    }
}
