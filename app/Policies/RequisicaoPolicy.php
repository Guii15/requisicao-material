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

        // Quem faz a transferência vê as transferências a partir da sua fila (depois, pela própria assinatura).
        if ($user->faz_transferencia
            && $requisicao->tipo === TipoRequisicao::TRANSFERENCIA_FILIAL
            && $requisicao->status === StatusRequisicao::AGUARDANDO_TRANSFERENCIA) {
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

        // Compra de funcionário: só quem está marcado como aprovador de compras (Kelber, Sérgio, Miguel).
        if ($requisicao->tipo === TipoRequisicao::COMPRA_FUNCIONARIO) {
            return $user->aprova_compras;
        }

        // Transferência de filial: líderes do estoque e líderes do Showroom (Ana Alice e Nicole), decisão de 16/11/2026.
        if ($requisicao->tipo === TipoRequisicao::TRANSFERENCIA_FILIAL) {
            return $this->fila->aprovaTransferencia($user);
        }

        // Uso e Consumo e Teste não passam pelo líder do setor: vão direto para os líderes do
        // estoque liberarem ou reprovarem (decisão de 17/11/2026). O Admin fica fora.
        return (bool) $user->is_lider_estoque;
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
        // Uso e Consumo chega aqui liberado pelo líder do estoque (ou aprovado por ele, que dispensa a liberação).
        return $user->ativo && $user->is_estoque && in_array($requisicao->status, StatusRequisicao::prontosParaSeparar(), true);
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
     * Só o Teste entrega (junto com a separação, na mesma tela); o Uso e Consumo não tem entrega.
     */
    public function entregar(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $user->is_estoque
            && $requisicao->tipo === TipoRequisicao::TESTE
            && $requisicao->status === StatusRequisicao::PRONTA_PARA_RETIRADA;
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
            || $requisicao->status !== StatusRequisicao::AGUARDANDO_BAIXA) {
            return false;
        }

        // A responsável pela baixa dá baixa em tudo, inclusive no que ela mesma pediu (17/11/2026).
        return (bool) $user->is_responsavel_baixa;
    }

    /**
     * Pedido de compra, depois dos aprovadores: a responsável pela baixa (Erica) aprova ou
     * reprova, inclusive o que ela mesma pediu.
     */
    public function decidirCompra(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $user->is_responsavel_baixa
            && $requisicao->tipo === TipoRequisicao::COMPRA_FUNCIONARIO
            && $requisicao->status === StatusRequisicao::AGUARDANDO_COMPRA;
    }

    /**
     * Transferência de filial aprovada: quem tem a permissão de fazer transferência (Isabelly)
     * transfere de uma empresa para a outra no WinThor.
     */
    public function transferir(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $user->faz_transferencia
            && $requisicao->tipo === TipoRequisicao::TRANSFERENCIA_FILIAL
            && $requisicao->status === StatusRequisicao::AGUARDANDO_TRANSFERENCIA;
    }

    /**
     * Transferido no WinThor: qualquer um do estoque envia o produto, com a foto da saída.
     */
    public function enviarTransferencia(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $user->is_estoque
            && $requisicao->tipo === TipoRequisicao::TRANSFERENCIA_FILIAL
            && $requisicao->status === StatusRequisicao::AGUARDANDO_ENVIO;
    }

    /**
     * Depois de enviado, quem pediu (desde que não tenha sido quem enviou) confirma que recebeu o produto (ou recusa).
     */
    public function receberTransferencia(User $user, Requisicao $requisicao): bool
    {
        return $user->ativo
            && $requisicao->tipo === TipoRequisicao::TRANSFERENCIA_FILIAL
            && $requisicao->status === StatusRequisicao::AGUARDANDO_RECEBIMENTO
            // Quem pediu confirma que chegou; quem enviou nunca confirma o próprio envio.
            && $requisicao->solicitante_id === $user->id
            && $requisicao->enviado_por_id !== $user->id;
    }

    public function recusarRecebimento(User $user, Requisicao $requisicao): bool
    {
        return $this->receberTransferencia($user, $requisicao);
    }

    public function cancelar(User $user, Requisicao $requisicao): bool
    {
        if (! $user->ativo) {
            return false;
        }

        // Quem pediu cancela enquanto ninguém decidiu (o pedido de compra já nasce esperando a Meire).
        if ($requisicao->solicitante_id === $user->id && in_array($requisicao->status, [StatusRequisicao::AGUARDANDO_APROVACAO, StatusRequisicao::AGUARDANDO_COMPRA], true)) {
            return true;
        }

        // Admin cancela em qualquer ponto antes da entrega, sempre com motivo.
        return $user->is_admin && $requisicao->status->podeIrPara(StatusRequisicao::CANCELADA, $requisicao->tipo);
    }
}
