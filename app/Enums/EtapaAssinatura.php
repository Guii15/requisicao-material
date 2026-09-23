<?php

namespace App\Enums;

enum EtapaAssinatura: string
{
    case SOLICITACAO = 'SOLICITACAO';
    case APROVACAO_SETOR = 'APROVACAO_SETOR';
    case REPROVACAO_SETOR = 'REPROVACAO_SETOR';
    case SEPARACAO = 'SEPARACAO';
    case LIBERACAO_ESTOQUE = 'LIBERACAO_ESTOQUE';
    case REPROVACAO_ESTOQUE = 'REPROVACAO_ESTOQUE';
    case ENTREGA = 'ENTREGA';
    case RETIRADA = 'RETIRADA';
    case RECEBIMENTO = 'RECEBIMENTO';
    case DEVOLUCAO = 'DEVOLUCAO';
    case BAIXA = 'BAIXA';

    /**
     * Texto da linha no bloco de assinaturas (tela e PDF).
     */
    public function rotulo(): string
    {
        return match ($this) {
            self::SOLICITACAO => 'Solicitado por',
            self::APROVACAO_SETOR => 'Aprovado por',
            self::REPROVACAO_SETOR => 'Reprovado por',
            self::SEPARACAO => 'Separado por',
            self::LIBERACAO_ESTOQUE => 'Liberado por (Líder do Estoque)',
            self::REPROVACAO_ESTOQUE => 'Reprovado por (Líder do Estoque)',
            self::ENTREGA => 'Entregue por',
            self::RETIRADA => 'Retirado por',
            self::RECEBIMENTO => 'Recebimento confirmado por',
            self::DEVOLUCAO => 'Devolução conferida por',
            self::BAIXA => 'Baixa por',
        };
    }

    /**
     * Etapas assinadas no caminho normal de cada tipo, na ordem (reprovações ficam fora).
     *
     * @return list<self>
     */
    public static function fluxo(TipoRequisicao $tipo): array
    {
        return $tipo === TipoRequisicao::TESTE
            ? [self::SOLICITACAO, self::APROVACAO_SETOR, self::SEPARACAO, self::ENTREGA, self::RETIRADA, self::RECEBIMENTO, self::DEVOLUCAO]
            : [self::SOLICITACAO, self::APROVACAO_SETOR, self::SEPARACAO, self::LIBERACAO_ESTOQUE, self::ENTREGA, self::RETIRADA, self::RECEBIMENTO, self::BAIXA];
    }
}
