<?php

namespace Tests\Unit\Enums;

use App\Enums\EtapaAssinatura;
use PHPUnit\Framework\TestCase;

class EtapaAssinaturaTest extends TestCase
{
    public function test_toda_etapa_tem_rotulo_para_o_bloco_de_assinaturas(): void
    {
        foreach (EtapaAssinatura::cases() as $etapa) {
            $this->assertStringContainsString(' por', $etapa->rotulo(), $etapa->value);
        }
    }

    public function test_rotulos_da_spec(): void
    {
        $this->assertSame('Solicitado por', EtapaAssinatura::SOLICITACAO->rotulo());
        $this->assertSame('Aprovado por', EtapaAssinatura::APROVACAO_SETOR->rotulo());
        $this->assertSame('Liberado por (Líder do Estoque)', EtapaAssinatura::LIBERACAO_ESTOQUE->rotulo());
        $this->assertSame('Entregue por', EtapaAssinatura::ENTREGA->rotulo());
        $this->assertSame('Retirado por', EtapaAssinatura::RETIRADA->rotulo());
        $this->assertSame('Baixa por', EtapaAssinatura::BAIXA->rotulo());
    }
}
