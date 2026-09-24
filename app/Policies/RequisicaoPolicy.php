<?php

namespace App\Policies;

use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\FilaDeAprovacao;

/**
 * Quem pode ver e agir em cada requisição. É a proteção real: a tela só esconde botões.
 */
class RequisicaoPolicy
{
    public function __construct(private readonly FilaDeAprovacao $fila) {}

    public function create(User $user): bool
    {
        return $user->ativo;
    }

    public function view(User $user, Requisicao $requisicao): bool
    {
        if ($user->is_admin || $requisicao->solicitante_id === $user->id || $user->aprovaSetor($requisicao->setor_id)) {
            return true;
        }

        // Quem pode decidir precisa ver; quem já assinou alguma etapa continua vendo.
        if ($this->aprovar($user, $requisicao) || $requisicao->assinaturas()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // O estoque nunca vê o que não foi aprovado.
        if (($user->is_estoque || $user->is_lider_estoque) && $requisicao->status->visivelParaEstoque()) {
            return true;
        }

        // A responsável pela baixa só vê Uso e Consumo a partir da fila de baixa.
        return $user->is_responsavel_baixa
            && $requisicao->tipo === TipoRequisicao::USO_CONSUMO
            && in_array($requisicao->status, [StatusRequisicao::AGUARDANDO_BAIXA, StatusRequisicao::BAIXADA], true);
    }

    public function aprovar(User $user, Requisicao $requisicao): bool
    {
        if (! $user->ativo
            || $requisicao->status !== StatusRequisicao::AGUARDANDO_APROVACAO
            || $requisicao->solicitante_id === $user->id) {
            return false;
        }

        if ($user->aprovaSetor($requisicao->setor_id)) {
            return true;
        }

        // Setor sem outro aprovador ativo: decidem os líderes do estoque. O Admin fica fora por enquanto.
        return $user->is_lider_estoque && $this->fila->semAprovadorElegivel($requisicao);
    }

    public function reprovar(User $user, Requisicao $requisicao): bool
    {
        return $this->aprovar($user, $requisicao);
    }

    public function cancelar(User $user, Requisicao $requisicao): bool
    {
        if (! $user->ativo) {
            return false;
        }

        if ($requisicao->solicitante_id === $user->id && $requisicao->status === StatusRequisicao::AGUARDANDO_APROVACAO) {
            return true;
        }

        // Admin cancela em qualquer ponto antes da entrega, sempre com motivo.
        return $user->is_admin && $requisicao->status->podeIrPara(StatusRequisicao::CANCELADA, $requisicao->tipo);
    }
}
