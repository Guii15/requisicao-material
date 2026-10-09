<?php

namespace Tests\Unit\Support;

use App\Enums\StatusRequisicao as S;
use App\Enums\TipoRequisicao as T;
use App\Support\Trilha;
use PHPUnit\Framework\TestCase;

class TrilhaTest extends TestCase
{
    /** @return list<string> */
    private function estados(S $status, T $tipo): array
    {
        return array_column(Trilha::etapas($status, $tipo), 'estado');
    }

    public function test_cada_tipo_tem_suas_cinco_etapas(): void
    {
        $this->assertSame(
            ['Aprovação', 'Separação', 'Liberação', 'Retirada', 'Baixa'],
            array_column(Trilha::etapas(S::APROVADA, T::USO_CONSUMO), 'nome'),
        );
        $this->assertSame(
            ['Aprovação', 'Separação', 'Retirada', 'Em posse', 'Devolução'],
            array_column(Trilha::etapas(S::APROVADA, T::TESTE), 'nome'),
        );
    }

    public function test_marca_a_etapa_atual_e_as_anteriores_como_feitas(): void
    {
        $this->assertSame(['atual', 'pendente', 'pendente', 'pendente', 'pendente'], $this->estados(S::AGUARDANDO_APROVACAO, T::USO_CONSUMO));
        $this->assertSame(['feita', 'feita', 'atual', 'pendente', 'pendente'], $this->estados(S::AGUARDANDO_LIBERACAO_ESTOQUE, T::USO_CONSUMO));
        $this->assertSame(['feita', 'feita', 'atual', 'pendente', 'pendente'], $this->estados(S::PRONTA_PARA_RETIRADA, T::TESTE));
    }

    public function test_requisicao_concluida_tem_tudo_feito(): void
    {
        $this->assertSame(array_fill(0, 5, 'feita'), $this->estados(S::BAIXADA, T::USO_CONSUMO));
        $this->assertSame(array_fill(0, 5, 'feita'), $this->estados(S::DEVOLVIDA, T::TESTE));
    }

    public function test_reprovacao_e_pendencia_marcam_a_etapa_do_problema(): void
    {
        $this->assertSame(['falha', 'pendente', 'pendente', 'pendente', 'pendente'], $this->estados(S::REPROVADA, T::USO_CONSUMO));
        $this->assertSame(['feita', 'feita', 'falha', 'pendente', 'pendente'], $this->estados(S::REPROVADA_ESTOQUE, T::USO_CONSUMO));
        $this->assertSame(['feita', 'feita', 'feita', 'feita', 'falha'], $this->estados(S::DEVOLUCAO_COM_PENDENCIA, T::TESTE));
    }

    public function test_cancelada_fica_toda_apagada(): void
    {
        $this->assertSame(array_fill(0, 5, 'cancelada'), $this->estados(S::CANCELADA, T::TESTE));
    }
}
