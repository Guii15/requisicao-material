<?php

namespace Tests\Unit\Support;

use App\Support\FeriadosNacionais;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FeriadosNacionaisTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function pascoas(): array
    {
        return [
            '2024' => [2024, '2024-03-31'],
            '2025' => [2025, '2025-04-20'],
            '2026' => [2026, '2026-04-05'],
            '2027' => [2027, '2027-03-28'],
            '2028' => [2028, '2028-04-16'],
        ];
    }

    #[DataProvider('pascoas')]
    public function test_calcula_a_pascoa(int $ano, string $esperado): void
    {
        $this->assertSame($esperado, FeriadosNacionais::pascoa($ano)->toDateString());
    }

    public function test_lista_feriados_nacionais_do_ano(): void
    {
        $feriados = FeriadosNacionais::doAno(2026);

        $this->assertSame([
            '2026-01-01' => 'Confraternização Universal',
            '2026-04-03' => 'Paixão de Cristo',
            '2026-04-21' => 'Tiradentes',
            '2026-05-01' => 'Dia do Trabalho',
            '2026-09-07' => 'Independência do Brasil',
            '2026-10-12' => 'Nossa Senhora Aparecida',
            '2026-11-02' => 'Finados',
            '2026-11-15' => 'Proclamação da República',
            '2026-11-20' => 'Dia Nacional de Zumbi e da Consciência Negra',
            '2026-12-25' => 'Natal',
        ], $feriados);
    }
}
