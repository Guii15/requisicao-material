<?php

namespace Tests\Unit\Support;

use App\Support\Duracao;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DuracaoTest extends TestCase
{
    public function test_formata_tempo_decorrido_de_forma_curta(): void
    {
        $agora = CarbonImmutable::parse('2026-09-24 10:00:00');

        $this->assertSame('agora', Duracao::curta($agora->subSeconds(40), $agora));
        $this->assertSame('5 min', Duracao::curta($agora->subMinutes(5), $agora));
        $this->assertSame('3 h', Duracao::curta($agora->subHours(3)->subMinutes(50), $agora));
        $this->assertSame('2 d', Duracao::curta($agora->subDays(2)->subHours(5), $agora));
    }

    public function test_frase_com_ha_para_textos_corridos(): void
    {
        $agora = CarbonImmutable::parse('2026-09-24 10:00:00');

        $this->assertSame('agora', Duracao::ha($agora->subSeconds(10), $agora));
        $this->assertSame('há 3 h', Duracao::ha($agora->subHours(3), $agora));
    }
}
