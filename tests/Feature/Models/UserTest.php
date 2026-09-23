<?php

namespace Tests\Feature\Models;

use App\Enums\PapelSetor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_senha_e_gravada_com_hash(): void
    {
        $user = User::factory()->create(['password' => 'segredo123']);

        $this->assertNotSame('segredo123', $user->getAttributes()['password']);
        $this->assertTrue(Hash::check('segredo123', $user->password));
    }

    public function test_papeis_nao_sao_preenchiveis_em_massa(): void
    {
        $user = new User;

        foreach (['is_estoque', 'is_lider_estoque', 'is_responsavel_baixa', 'is_admin', 'ativo', 'deve_trocar_senha'] as $campo) {
            $this->assertFalse($user->isFillable($campo), "{$campo} não pode ser preenchível em massa");
        }
    }

    public function test_aprovador_aprova_somente_os_setores_vinculados(): void
    {
        $ti = Setor::factory()->create(['nome' => 'TI']);
        $financeiro = Setor::factory()->create(['nome' => 'FINANCEIRO']);
        $lider = User::factory()->aprovadorDe($ti)->create();

        $this->assertTrue($lider->aprovaSetor($ti));
        $this->assertFalse($lider->aprovaSetor($financeiro));
    }

    public function test_aprovador_pode_aprovar_setor_diferente_do_seu(): void
    {
        // Caso real: os líderes do Estoque aprovam os pedidos do Showroom.
        $estoque = Setor::factory()->create(['nome' => 'ESTOQUE']);
        $showroom = Setor::factory()->create(['nome' => 'SHOWROOM']);
        $geisla = User::factory()->for($estoque)->aprovadorDe($estoque)->aprovadorDe($showroom)->create();

        $this->assertTrue($geisla->aprovaSetor($showroom));
        $this->assertSame($estoque->id, $geisla->setor_id);
    }

    public function test_setor_lista_aprovadores_com_papel(): void
    {
        $setor = Setor::factory()->create();
        User::factory()->aprovadorDe($setor, PapelSetor::LIDER)->create(['nome' => 'Líder']);
        User::factory()->aprovadorDe($setor, PapelSetor::SUBLIDER)->create(['nome' => 'Sublíder']);

        $papeis = $setor->aprovadores()->orderBy('nome')->get()
            ->mapWithKeys(fn (User $u) => [$u->nome => $u->pivot->papel])
            ->all();

        $this->assertSame(['Líder' => PapelSetor::LIDER, 'Sublíder' => PapelSetor::SUBLIDER], $papeis);
    }

    public function test_mesmo_usuario_nao_e_aprovador_duas_vezes_no_mesmo_setor(): void
    {
        $setor = Setor::factory()->create();
        $user = User::factory()->aprovadorDe($setor)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        $setor->aprovadores()->attach($user->id, ['papel' => PapelSetor::SUBLIDER]);
    }
}
