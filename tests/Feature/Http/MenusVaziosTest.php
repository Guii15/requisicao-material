<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * Menus que ficariam sempre vazios não aparecem (23/11/2026).
 */
class MenusVaziosTest extends TestCase
{
    use CenarioRequisicao;
    use RefreshDatabase;

    public function test_lider_de_setor_que_nao_aprova_nada_nao_ve_aprovacoes(): void
    {
        [, $lider] = $this->setorComAprovadores('FINANCEIRO', lideresDoEstoque: false);

        $this->assertFalse(Gate::forUser($lider)->allows('acessar-aprovacoes'));
    }

    public function test_quem_aprova_continua_vendo_aprovacoes(): void
    {
        [, $liderShowroom] = $this->setorComAprovadores('SHOWROOM', lideresDoEstoque: false);
        $liderEstoque = User::factory()->create(['is_lider_estoque' => true]);
        $compras = User::factory()->create(['aprova_compras' => true]);

        $this->assertTrue(Gate::forUser($liderShowroom)->allows('acessar-aprovacoes'));
        $this->assertTrue(Gate::forUser($liderEstoque)->allows('acessar-aprovacoes'));
        $this->assertTrue(Gate::forUser($compras)->allows('acessar-aprovacoes'));
    }

    public function test_liberacao_so_aparece_quando_tem_algo_para_liberar(): void
    {
        $liderEstoque = User::factory()->create(['is_lider_estoque' => true]);

        $this->assertFalse(Gate::forUser($liderEstoque)->allows('acessar-liberacao'));
    }
}
