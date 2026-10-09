<?php

namespace Tests\Feature\Http;

use App\Models\RequisicaoEvento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class PainelTelasTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-09 10:00:00');
    }

    public function test_filtro_de_mes_mostra_so_a_atividade_daquele_mes(): void
    {
        [$setor] = $this->setorComAprovadores();
        $solicitante = User::factory()->for($setor)->create();

        $this->travelTo('2026-09-24 10:00:00');
        $antiga = $this->abrirRequisicao($solicitante);

        $this->travelTo('2026-10-09 10:00:00');
        $recente = $this->abrirRequisicao($solicitante, ['data_prevista_devolucao' => '2026-10-12']);

        $this->actingAs($solicitante)->get('/painel?mes=2026-09')
            ->assertOk()
            ->assertSee($antiga->numero)
            ->assertDontSee($recente->numero);

        $this->actingAs($solicitante)->get('/painel')
            ->assertOk()
            ->assertSee($recente->numero)
            ->assertSee($antiga->numero);
    }

    public function test_mes_fora_do_formato_e_ignorado(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)->get('/painel?mes=qualquer-coisa')->assertOk();
        $this->assertSame(0, RequisicaoEvento::count());
    }

    public function test_filtro_oferece_os_ultimos_doze_meses(): void
    {
        $this->actingAs(User::factory()->create())->get('/painel')
            ->assertOk()
            ->assertSee('Outubro de 2026')
            ->assertSee('Novembro de 2025');
    }
}
