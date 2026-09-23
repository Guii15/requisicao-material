<?php

namespace Tests\Unit\Support;

use App\Support\DiasUteis;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DiasUteisTest extends TestCase
{
    public function test_soma_dias_uteis_pulando_fim_de_semana(): void
    {
        // Quinta 24/09/2026 + 3 dias úteis = sexta 25, segunda 28, terça 29.
        $dias = new DiasUteis([]);

        $this->assertSame('2026-09-29', $dias->somar(CarbonImmutable::parse('2026-09-24'), 3)->toDateString());
    }

    public function test_soma_dias_uteis_pulando_feriado(): void
    {
        // Quinta 08/10/2026 + 3 com feriado na segunda 12/10 = sexta 9, terça 13, quarta 14.
        $dias = new DiasUteis(['2026-10-12']);

        $this->assertSame('2026-10-14', $dias->somar(CarbonImmutable::parse('2026-10-08'), 3)->toDateString());
    }

    public function test_pedido_feito_no_sabado_conta_a_partir_de_segunda(): void
    {
        $dias = new DiasUteis([]);

        $this->assertSame('2026-09-30', $dias->somar(CarbonImmutable::parse('2026-09-26'), 3)->toDateString());
    }

    public function test_ignora_horario_da_data_de_origem(): void
    {
        $dias = new DiasUteis([]);

        $resultado = $dias->somar(CarbonImmutable::parse('2026-09-24 17:45:00'), 3);

        $this->assertSame('2026-09-29 00:00:00', $resultado->toDateTimeString());
    }

    public function test_identifica_dia_util(): void
    {
        $dias = new DiasUteis(['2026-11-02']);

        $this->assertTrue($dias->ehDiaUtil(CarbonImmutable::parse('2026-11-03')));
        $this->assertFalse($dias->ehDiaUtil(CarbonImmutable::parse('2026-11-02')));
        $this->assertFalse($dias->ehDiaUtil(CarbonImmutable::parse('2026-11-01')));
    }
}
