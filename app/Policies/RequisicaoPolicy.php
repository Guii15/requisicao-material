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

    /**
     * Qualquer um do setor Estoque separa qualquer requisição aprovada, de qualquer setor.
     * (Sem regra de "não separar a própria" registrada ainda — só a de liberação tem essa restrição.)
     */
    public function separar(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo && $user->is_estoque && $requisicao->status === StatusRequisicao::APROVADA;
    }

    /**
     * Só líder do estoque, só Uso e Consumo, e nunca quem aprovou ou quem separou essa mesma
     * requisição (antifraude: quem libera não pode ser quem já deu ok em outra etapa dela).
     */
    public function liberar(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $user->is_lider_estoque
            && $requisicao->tipo === TipoRequisicao::USO_CONSUMO
            && $requisicao->status === StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE
            && $requisicao->aprovado_por_id !== $user->id
            && $requisicao->separado_por_id !== $user->id;
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
     * Só o próprio solicitante confirma que recebeu, e só no Teste (é o único tipo com essa etapa).
     */
    public function confirmarRecebimento(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $requisicao->solicitante_id === $user->id
            && $requisicao->tipo === TipoRequisicao::TESTE
            && $requisicao->status === StatusRequisicao::EM_POSSE;
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
