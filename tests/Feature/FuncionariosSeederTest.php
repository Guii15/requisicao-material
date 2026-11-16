<?php

namespace Tests\Feature;

use App\Models\Setor;
use App\Models\User;
use Database\Seeders\FuncionariosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FuncionariosSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_carrega_os_funcionarios_com_setores_e_papeis(): void
    {
        $this->seed(FuncionariosSeeder::class);

        $this->assertSame(49, User::query()->where('ativo', true)->count());

        $kelber = User::where('login', 'kelber.vilela')->firstOrFail();
        $this->assertTrue($kelber->is_estoque && $kelber->is_lider_estoque && $kelber->aprova_compras);
        $this->assertTrue($kelber->deve_trocar_senha);
        $this->assertTrue(Hash::check('Bin@2026', $kelber->password));

        $this->assertTrue(User::where('login', 'guilherme.santana')->firstOrFail()->is_admin);
        $this->assertTrue(User::where('login', 'cesar.marcelino')->firstOrFail()->is_admin);
        $this->assertTrue(User::where('login', 'meire.vale')->firstOrFail()->is_responsavel_baixa);
        $this->assertTrue(User::where('login', 'isabelly.santos')->firstOrFail()->faz_transferencia);

        $showroom = Setor::where('nome', 'SHOWROOM')->firstOrFail();
        $aprovadores = $showroom->aprovadores()->pluck('login')->sort()->values()->all();
        $this->assertContains('ana.oliveira', $aprovadores);
        $this->assertContains('nicole.assis', $aprovadores);
        $this->assertContains('miguel.araujo', $aprovadores);
    }

    public function test_rodar_de_novo_nao_duplica_nem_troca_senha(): void
    {
        $this->seed(FuncionariosSeeder::class);
        $kelber = User::where('login', 'kelber.vilela')->firstOrFail();
        $kelber->forceFill(['password' => 'OutraSenha#1', 'deve_trocar_senha' => false])->save();

        $this->seed(FuncionariosSeeder::class);

        $this->assertSame(49, User::query()->count());
        $this->assertTrue(Hash::check('OutraSenha#1', $kelber->fresh()->password));
    }

    public function test_rodar_de_novo_nao_tira_admin_de_ninguem(): void
    {
        $this->seed(FuncionariosSeeder::class);
        $hayla = User::where('login', 'hayla.souza')->firstOrFail();
        $hayla->forceFill(['is_admin' => true])->save();

        $this->seed(FuncionariosSeeder::class);

        $this->assertTrue($hayla->fresh()->is_admin);
        $this->assertTrue(User::where('login', 'cesar.marcelino')->firstOrFail()->is_admin);
        $this->assertTrue(User::where('login', 'guilherme.santana')->firstOrFail()->is_admin);
    }
}
