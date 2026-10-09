<?php

namespace Tests\Unit\Enums;

use App\Enums\EtapaAssinatura as E;
use App\Enums\TipoRequisicao;
use PHPUnit\Framework\TestCase;

class EtapaAssinaturaTest extends TestCase
{
    public function test_toda_etapa_tem_rotulo_para_o_bloco_de_assinaturas(): void
    {
        foreach (E::cases() as $etapa) {
            $this->assertStringContainsString(' por', $etapa->rotulo(), $etapa->value);
        }
    }

    public function test_rotulos_da_spec(): void
    {
        $this->assertSame('Solicitado por', E::SOLICITACAO->rotulo());
        $this->assertSame('Aprovado por', E::APROVACAO_SETOR->rotulo());
        $this->assertSame('Liberado por (Líder do Estoque)', E::LIBERACAO_ESTOQUE->rotulo());
        $this->assertSame('Entregue por', E::ENTREGA->rotulo());
        $this->assertSame('Retirado por', E::RETIRADA->rotulo());
        $this->assertSame('Devolvido por', E::DEVOLVIDO_POR->rotulo());
        $this->assertSame('Baixa por', E::BAIXA->rotulo());
    }

    public function test_fluxo_de_assinaturas_por_tipo(): void
    {
        $this->assertSame(
            [E::SOLICITACAO, E::APROVACAO_SETOR, E::SEPARACAO, E::ENTREGA, E::DEVOLUCAO],
            E::fluxo(TipoRequisicao::TESTE),
        );
        $this->assertSame(
            [E::SOLICITACAO, E::APROVACAO_SETOR, E::SEPARACAO, E::LIBERACAO_ESTOQUE, E::ENTREGA, E::BAIXA],
            E::fluxo(TipoRequisicao::USO_CONSUMO),
        );
    }
}
