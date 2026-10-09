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

        // Compra de funcionário não passa pelo estoque: quem decide é a responsável pela baixa.
        if ($requisicao->tipo === TipoRequisicao::COMPRA_FUNCIONARIO) {
            return $user->is_responsavel_baixa
                && in_array($requisicao->status, [StatusRequisicao::AGUARDANDO_COMPRA, StatusRequisicao::COMPRA_APROVADA, StatusRequisicao::COMPRA_REPROVADA], true);
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

        // Compra de funcionário: só quem está marcado como aprovador de compras (Kelber, Sérgio, Miguel).
        if ($requisicao->tipo === TipoRequisicao::COMPRA_FUNCIONARIO) {
            return $user->aprova_compras;
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

    /**
     * Qualquer um do setor Estoque separa qualquer requisição aprovada, de qualquer setor.
     * Sem trava de pessoa: quem separa pode ser quem entrega e quem confere a devolução.
     */
    public function separar(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo && $user->is_estoque && $requisicao->status === StatusRequisicao::APROVADA;
    }

    /**
     * Separar e entregar na mesma tela: só no Teste (no Uso e Consumo o líder libera no meio).
     */
    public function separarEntregar(User $user, Requisicao $requisicao): bool
    {
        return $requisicao->tipo === TipoRequisicao::TESTE && $this->separar($user, $requisicao);
    }

    /**
     * Só líder do estoque e só Uso e Consumo. Não há trava de "quem separou não libera": no
     * estoque, quem tem o papel faz a etapa, sem separar quem faz de quem confere.
     */
    public function liberar(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $user->is_lider_estoque
            && $requisicao->tipo === TipoRequisicao::USO_CONSUMO
            && $requisicao->status === StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE;
    }

    public function reprovarEstoque(User $user, Requisicao $requisicao): bool
    {
        return $this->liberar($user, $requisicao);
    }

    /**
     * Qualquer um do estoque entrega (o desenho de quem retira não precisa de login).
     */
    public function entregar(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo && $user->is_estoque && $requisicao->status === StatusRequisicao::PRONTA_PARA_RETIRADA;
    }

    /**
     * Qualquer um do estoque confere a devolução (Teste).
     */
    public function devolver(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $user->is_estoque
            && $requisicao->tipo === TipoRequisicao::TESTE
            && $requisicao->status === StatusRequisicao::EM_POSSE;
    }

    /**
     * A responsável pela baixa, nunca no próprio pedido. Se ela mesma abriu (ex.: Erica do
     * RH), a baixa cai pro Admin — decisão de 24/09/2026, igual à fila do Admin pra aprovação.
     */
    public function darBaixa(User $user, Requisicao $requisicao): bool
    {
        if (! $user->ativo
            || $requisicao->tipo !== TipoRequisicao::USO_CONSUMO
            || $requisicao->status !== StatusRequisicao::AGUARDANDO_BAIXA
            || $requisicao->solicitante_id === $user->id) {
            return false;
        }

        if ($user->is_responsavel_baixa) {
            return true;
        }

        return $user->is_admin && $requisicao->solicitante->is_responsavel_baixa;
    }

    /**
     * Compra de funcionário, depois dos aprovadores: a responsável pela baixa (Erica) aprova ou
     * reprova. Nunca o próprio pedido; se ela mesma abriu, quem decide é o Admin.
     */
    public function decidirCompra(User $user, Requisicao $requisicao): bool
    {
        if (! $user->ativo
            || $requisicao->tipo !== TipoRequisicao::COMPRA_FUNCIONARIO
            || $requisicao->status !== StatusRequisicao::AGUARDANDO_COMPRA
            || $requisicao->solicitante_id === $user->id) {
            return false;
        }

        if ($user->is_responsavel_baixa) {
            return true;
        }

        return $user->is_admin && $requisicao->loadMissing('solicitante')->solicitante->is_responsavel_baixa;
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
