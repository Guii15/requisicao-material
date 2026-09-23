<?php

namespace Tests\Unit\Enums;

use App\Enums\TipoRequisicao;
use PHPUnit\Framework\TestCase;

class TipoRequisicaoTest extends TestCase
{
    public function test_somente_teste_exige_devolucao(): void
    {
        $this->assertTrue(TipoRequisicao::TESTE->exigeDevolucao());
        $this->assertFalse(TipoRequisicao::USO_CONSUMO->exigeDevolucao());
    }

    public function test_somente_uso_e_consumo_exige_liberacao_e_baixa(): void
    {
        $this->assertTrue(TipoRequisicao::USO_CONSUMO->exigeLiberacaoEstoque());
        $this->assertTrue(TipoRequisicao::USO_CONSUMO->exigeBaixa());
        $this->assertFalse(TipoRequisicao::TESTE->exigeLiberacaoEstoque());
        $this->assertFalse(TipoRequisicao::TESTE->exigeBaixa());
    }

    public function test_rotulos_sao_textos_distintos(): void
    {
        // Diferença visual nunca pode ser só cor: o texto do selo precisa ser diferente.
        $this->assertSame('TESTE', TipoRequisicao::TESTE->rotulo());
        $this->assertSame('USO E CONSUMO', TipoRequisicao::USO_CONSUMO->rotulo());
    }
}
