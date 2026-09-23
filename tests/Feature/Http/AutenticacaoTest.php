<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_de_login_abre(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('Entrar')->assertSee('Usuário');
    }

    public function test_visitante_e_mandado_para_o_login(): void
    {
        $this->get('/requisicoes')->assertRedirect('/entrar');
    }

    public function test_entra_com_login_e_senha_ignorando_maiusculas_e_espacos(): void
    {
        $user = User::factory()->create(['login' => 'geisla.luiz']);

        $this->post('/entrar', ['login' => ' Geisla.Luiz ', 'senha' => 'password'])->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_senha_errada_nao_entra_e_a_senha_nao_volta_para_a_sessao(): void
    {
        User::factory()->create(['login' => 'geisla.luiz']);

        $this->from('/entrar')->post('/entrar', ['login' => 'geisla.luiz', 'senha' => 'errada'])
            ->assertRedirect('/entrar')
            ->assertSessionHasErrors(['login' => 'Usuário ou senha incorretos.']);

        $this->assertGuest();
        $this->assertSame('geisla.luiz', session()->getOldInput('login'));
        $this->assertNull(session()->getOldInput('senha'));
    }

    public function test_usuario_inativo_nao_entra(): void
    {
        User::factory()->inativo()->create(['login' => 'ex.funcionario']);

        $this->post('/entrar', ['login' => 'ex.funcionario', 'senha' => 'password'])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_usuario_desativado_durante_a_sessao_e_desconectado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['ativo' => false])->save();

        $this->get('/requisicoes')->assertRedirect('/entrar');

        $this->assertGuest();
    }

    public function test_login_e_limitado_a_cinco_tentativas_por_minuto(): void
    {
        User::factory()->create(['login' => 'alvo']);

        foreach (range(1, 5) as $tentativa) {
            $this->post('/entrar', ['login' => 'alvo', 'senha' => "errada{$tentativa}"]);
        }

        $this->post('/entrar', ['login' => 'alvo', 'senha' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_primeiro_acesso_exige_troca_de_senha(): void
    {
        $this->actingAs(User::factory()->create(['deve_trocar_senha' => true]));

        $this->get('/requisicoes')->assertRedirect('/senha');
        $this->get('/senha')->assertOk()->assertSee('Defina sua senha');
    }

    public function test_troca_de_senha(): void
    {
        $user = User::factory()->create(['deve_trocar_senha' => true]);

        $this->actingAs($user)->put('/senha', [
            'senha_atual' => 'password',
            'nova_senha' => 'Estoque2026',
            'nova_senha_confirmation' => 'Estoque2026',
        ])->assertRedirect(route('inicio'));

        $user->refresh();
        $this->assertFalse($user->deve_trocar_senha);
        $this->assertTrue(Hash::check('Estoque2026', $user->password));
        $this->assertNull(session()->getOldInput('nova_senha'));
    }

    public function test_troca_de_senha_exige_senha_atual_correta_e_nova_diferente(): void
    {
        $this->actingAs(User::factory()->create(['deve_trocar_senha' => true]));

        $this->put('/senha', ['senha_atual' => 'errada', 'nova_senha' => 'Estoque2026', 'nova_senha_confirmation' => 'Estoque2026'])
            ->assertSessionHasErrors('senha_atual');
        $this->put('/senha', ['senha_atual' => 'password', 'nova_senha' => 'password', 'nova_senha_confirmation' => 'password'])
            ->assertSessionHasErrors('nova_senha');
    }

    public function test_sair(): void
    {
        $this->actingAs(User::factory()->create())->post('/sair')->assertRedirect('/entrar');

        $this->assertGuest();
    }
}
