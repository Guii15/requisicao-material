<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Botão do admin: quem ainda está com a senha inicial cria a própria senha no próximo acesso (23/11/2026).
 */
class ExigirTrocaSenhaInicialTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_so_quem_ainda_esta_com_a_senha_inicial(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'deve_trocar_senha' => false]);
        $comInicial = User::factory()->create(['password' => 'Bin@2026', 'deve_trocar_senha' => false]);
        $jaTrocou = User::factory()->create(['password' => 'OutraSenha123', 'deve_trocar_senha' => false]);

        $this->actingAs($admin)->post(route('usuarios.exigir-troca-senha'))
            ->assertRedirect(route('usuarios.index'))
            ->assertSessionHas('sucesso');

        $this->assertTrue($comInicial->fresh()->deve_trocar_senha);
        $this->assertFalse($jaTrocou->fresh()->deve_trocar_senha);

        $this->actingAs($comInicial->fresh())->get(route('painel.index'))->assertRedirect(route('senha.edit'));
    }

    public function test_quem_nao_e_admin_nao_pode(): void
    {
        $user = User::factory()->create(['deve_trocar_senha' => false]);

        $this->actingAs($user)->post(route('usuarios.exigir-troca-senha'))->assertForbidden();
    }
}
