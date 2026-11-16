<?php

namespace Tests\Feature\Http;

use App\Enums\PapelSetor;
use App\Models\Setor;
use App\Models\User;
use App\Models\UsuarioAlteracao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UsuariosTelasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $sobrescrever
     * @return array<string, mixed>
     */
    private function dados(Setor $setor, array $sobrescrever = []): array
    {
        return array_merge([
            'nome' => 'Fulana de Tal',
            'login' => 'fulana.tal',
            'matricula' => '777',
            'cargo' => 'Vendedora',
            'setor_id' => $setor->id,
            'papel_setor' => '',
            'senha' => 'Inicio2026',
            'ativo' => '1',
            'is_estoque' => '0',
            'is_lider_estoque' => '0',
            'is_responsavel_baixa' => '0',
            'aprova_compras' => '0',
            'is_admin' => '0',
        ], $sobrescrever);
    }

    public function test_quem_nao_e_admin_nao_ve_o_menu_nem_a_tela(): void
    {
        $user = User::factory()->create();
        $outro = User::factory()->create();

        $this->actingAs($user)->get('/requisicoes')->assertOk()->assertDontSee('Usuários e permissões');
        $this->actingAs($user)->get('/usuarios')->assertForbidden();
        $this->actingAs($user)->get('/usuarios/novo')->assertForbidden();
        $this->actingAs($user)->get("/usuarios/{$outro->id}")->assertForbidden();
        $this->actingAs($user)->post('/usuarios', $this->dados($user->setor))->assertForbidden();
        $this->actingAs($user)->put("/usuarios/{$outro->id}", $this->dados($outro->setor, ['is_admin' => '1']))->assertForbidden();
        $this->actingAs($user)->post("/usuarios/{$outro->id}/resetar-senha", ['senha' => 'Nova12345'])->assertForbidden();

        $this->assertFalse($outro->fresh()->is_admin);
        $this->assertSame(0, User::where('login', 'fulana.tal')->count());
    }

    public function test_admin_ve_o_menu_e_a_lista(): void
    {
        $admin = User::factory()->admin()->create();
        $pessoa = User::factory()->create(['nome' => 'Joana Teste']);

        $this->actingAs($admin)->get('/requisicoes')->assertSee('Usuários e permissões');
        $this->actingAs($admin)->get('/usuarios')->assertOk()->assertSee('Joana Teste')->assertSee($pessoa->login);
        $this->actingAs($admin)->get('/usuarios?busca=Joana')->assertOk()->assertSee('Joana Teste')->assertDontSee($admin->nome.'</a>', false);
    }

    public function test_admin_cria_login_com_permissoes_e_a_pessoa_troca_a_senha_no_primeiro_acesso(): void
    {
        $admin = User::factory()->admin()->create();
        $showroom = Setor::factory()->create(['nome' => 'SHOWROOM']);
        $ninja = Setor::factory()->create(['nome' => 'NINJA PLACE']);

        $this->actingAs($admin)->post('/usuarios', $this->dados($showroom, [
            'papel_setor' => 'SUBLIDER',
            'aprova_setores' => [(string) $ninja->id],
            'aprova_compras' => '1',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $nova = User::where('login', 'fulana.tal')->firstOrFail();
        $this->assertTrue(Hash::check('Inicio2026', $nova->password));
        $this->assertTrue($nova->deve_trocar_senha);
        $this->assertTrue($nova->ativo);
        $this->assertTrue($nova->aprova_compras);
        $this->assertFalse($nova->is_admin);
        $this->assertSame(777, $nova->matricula);
        $this->assertEqualsCanonicalizing(
            [$showroom->id => PapelSetor::SUBLIDER, $ninja->id => PapelSetor::LIDER],
            $nova->setoresAprovados()->get()->mapWithKeys(fn ($s) => [$s->id => $s->pivot->papel])->all(),
        );
        $this->assertSame('CRIACAO', UsuarioAlteracao::where('usuario_id', $nova->id)->value('acao'));

        $this->post('/sair');
        $this->post('/entrar', ['login' => 'fulana.tal', 'senha' => 'Inicio2026'])->assertRedirect();
        $this->get('/requisicoes')->assertRedirect('/senha');
    }

    public function test_login_repetido_e_senha_fraca_sao_recusados(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['login' => 'fulana.tal']);

        $this->actingAs($admin)->post('/usuarios', $this->dados($admin->setor, ['senha' => 'curta']))
            ->assertSessionHasErrors(['login', 'senha']);
    }

    public function test_permissao_de_estoque_so_para_quem_e_do_estoque(): void
    {
        $admin = User::factory()->admin()->create();
        $ti = Setor::factory()->create(['nome' => 'TI']);
        $estoque = Setor::factory()->create(['nome' => 'ESTOQUE']);

        $this->actingAs($admin)->post('/usuarios', $this->dados($ti, ['is_estoque' => '1']))
            ->assertSessionHasErrors('is_estoque');
        $this->actingAs($admin)->post('/usuarios', $this->dados($estoque, ['is_lider_estoque' => '1']))
            ->assertSessionHasErrors('is_lider_estoque');
        $this->actingAs($admin)->post('/usuarios', $this->dados($estoque, ['is_estoque' => '1', 'is_lider_estoque' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertTrue(User::where('login', 'fulana.tal')->value('is_lider_estoque'));
    }

    public function test_alterar_permissoes_fica_no_historico_com_o_que_mudou(): void
    {
        $admin = User::factory()->admin()->create(['nome' => 'Admin Chefe']);
        $setor = Setor::factory()->create();
        $pessoa = User::factory()->for($setor)->aprovadorDe($setor, PapelSetor::LIDER)->create();

        $this->actingAs($admin)->put("/usuarios/{$pessoa->id}", $this->dados($setor, [
            'nome' => $pessoa->nome,
            'login' => $pessoa->login,
            'matricula' => (string) $pessoa->matricula,
            'cargo' => $pessoa->cargo,
            'papel_setor' => '',
            'is_responsavel_baixa' => '1',
        ]))->assertSessionHasNoErrors()->assertRedirect("/usuarios/{$pessoa->id}");

        $pessoa->refresh();
        $this->assertTrue($pessoa->is_responsavel_baixa);
        $this->assertFalse($pessoa->aprovaSetor($setor));

        $alteracao = UsuarioAlteracao::where('usuario_id', $pessoa->id)->where('acao', 'ALTERACAO')->firstOrFail();
        $this->assertSame($admin->id, $alteracao->feito_por_id);
        $this->assertSame(['de' => 'Líder', 'para' => '—'], $alteracao->mudancas['Papel no setor']);
        $this->assertArrayNotHasKey('Nome', $alteracao->mudancas);

        $this->actingAs($admin)->get("/usuarios/{$pessoa->id}")->assertOk()->assertSee('Admin Chefe')->assertSee('Papel no setor: Líder');
    }

    public function test_desativar_bloqueia_o_acesso_na_hora(): void
    {
        $admin = User::factory()->admin()->create();
        $pessoa = User::factory()->create();

        $this->actingAs($admin)->put("/usuarios/{$pessoa->id}", $this->dados($pessoa->setor, [
            'nome' => $pessoa->nome, 'login' => $pessoa->login, 'matricula' => (string) $pessoa->matricula, 'ativo' => '0',
        ]))->assertSessionHasNoErrors();

        $this->assertFalse($pessoa->fresh()->ativo);
        $this->actingAs($pessoa->fresh())->get('/requisicoes')->assertRedirect('/entrar');
    }

    public function test_admin_nao_tira_o_proprio_acesso(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();
        $base = ['nome' => $admin->nome, 'login' => $admin->login, 'matricula' => (string) $admin->matricula, 'is_admin' => '1'];

        $this->actingAs($admin)->put("/usuarios/{$admin->id}", $this->dados($admin->setor, [...$base, 'is_admin' => '0']))
            ->assertSessionHasErrors('is_admin');
        $this->actingAs($admin)->put("/usuarios/{$admin->id}", $this->dados($admin->setor, [...$base, 'ativo' => '0']))
            ->assertSessionHasErrors('ativo');

        $this->assertTrue($admin->fresh()->is_admin);
        $this->assertTrue($admin->fresh()->ativo);
    }

    public function test_resetar_senha_obriga_a_troca_e_fica_no_historico(): void
    {
        $admin = User::factory()->admin()->create();
        $pessoa = User::factory()->create();

        $this->actingAs($admin)->post("/usuarios/{$pessoa->id}/resetar-senha", ['senha' => 'Reset2026'])
            ->assertSessionHasNoErrors()->assertRedirect("/usuarios/{$pessoa->id}");

        $pessoa->refresh();
        $this->assertTrue(Hash::check('Reset2026', $pessoa->password));
        $this->assertTrue($pessoa->deve_trocar_senha);
        $this->assertSame('SENHA_RESETADA', UsuarioAlteracao::where('usuario_id', $pessoa->id)->value('acao'));
    }

    public function test_comando_marca_e_desmarca_administradores(): void
    {
        $guilherme = User::factory()->create(['login' => 'guilherme.santana']);
        $cesar = User::factory()->create(['login' => 'cesar.marcelino']);

        $this->artisan('usuarios:admin', ['login' => ['guilherme.santana', 'cesar.marcelino']])->assertSuccessful();
        $this->assertTrue($guilherme->fresh()->is_admin && $cesar->fresh()->is_admin);

        $this->artisan('usuarios:admin', ['login' => ['nao.existe']])->assertFailed();

        $this->artisan('usuarios:admin', ['login' => ['cesar.marcelino'], '--remover' => true])->assertSuccessful();
        $this->assertFalse($cesar->fresh()->is_admin);

        // O último administrador ativo não sai.
        $this->artisan('usuarios:admin', ['login' => ['guilherme.santana'], '--remover' => true])->assertFailed();
        $this->assertTrue($guilherme->fresh()->is_admin);
    }
}
