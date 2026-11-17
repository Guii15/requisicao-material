<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FilaDeAprovacao;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemonstracaoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demonstracao_segue_as_regras_de_aprovacao(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        $this->seed(DemonstracaoSeeder::class);

        $fila = app(FilaDeAprovacao::class);
        $setoresNaFila = fn (string $login) => $fila->para(User::where('login', $login)->firstOrFail())
            ->with('setor')->get()->pluck('setor.nome')->sort()->values()->all();

        // Desde 17/11/2026 todo pedido vai direto para o líder do estoque; o líder do setor não aprova.
        $this->assertSame(['RH', 'SHOWROOM', 'TI', 'TI'], $setoresNaFila('demo.liderestoque'));
        $this->assertSame([], $setoresNaFila('demo.lider'));
        $this->assertSame([], $setoresNaFila('demo.admin'));
    }

    public function test_usuarios_de_demonstracao_tem_nome_de_pessoa(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        $this->seed(DemonstracaoSeeder::class);

        $this->assertSame(0, User::where('nome', 'like', '%demonstra%')->count());
        $this->assertSame('Leandro Moreira', User::where('login', 'demo.lider')->value('nome'));
    }
}
