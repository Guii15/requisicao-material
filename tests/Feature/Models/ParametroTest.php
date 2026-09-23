<?php

namespace Tests\Feature\Models;

use App\Models\Parametro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametroTest extends TestCase
{
    use RefreshDatabase;

    public function test_parametros_padrao_existem_depois_da_migration(): void
    {
        $this->assertSame(3, Parametro::inteiro('prazo_max_devolucao_dias'));
        $this->assertSame(24, Parametro::inteiro('horas_confirmar_recebimento'));
        $this->assertTrue(Parametro::booleano('exigir_liberador_diferente_do_separador'));
    }

    public function test_parametro_inexistente_gera_erro(): void
    {
        // Parâmetro digitado errado não pode virar "0" ou "false" em silêncio numa regra de negócio.
        $this->expectException(\InvalidArgumentException::class);

        Parametro::inteiro('parametro_que_nao_existe');
    }
}
