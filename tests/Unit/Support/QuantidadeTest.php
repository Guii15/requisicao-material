<?php

namespace Tests\Unit\Support;

use App\Support\Quantidade;
use PHPUnit\Framework\TestCase;

class QuantidadeTest extends TestCase
{
    public function test_formata_no_padrao_brasileiro_sem_zeros_sobrando(): void
    {
        $this->assertSame('1', Quantidade::formatar('1.000'));
        $this->assertSame('2,5', Quantidade::formatar('2.500'));
        $this->assertSame('1.234,125', Quantidade::formatar('1234.125'));
        $this->assertSame('-', Quantidade::formatar(null));
    }
}
