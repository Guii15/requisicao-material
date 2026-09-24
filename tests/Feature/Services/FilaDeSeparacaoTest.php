<?php

namespace Tests\Feature\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\FilaDeSeparacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class FilaDeSeparacaoTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private FilaDeSeparacao $fila;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fila = app(FilaDeSeparacao::class);
    }

    public function test_fila_mostra_as_aprovadas_de_qualquer_setor(): void
    {
        $estoquista = User::factory()->estoque()->create();
        $ti = Requisicao::factory()->status(StatusRequisicao::APROVADA)->create();
        $rh = Requisicao::factory()->status(StatusRequisicao::APROVADA)->create();

        $numeros = $this->fila->para($estoquista)->pluck('numero')->all();

        $this->assertContains($ti->numero, $numeros);
        $this->assertContains($rh->numero, $numeros);
    }

    public function test_fila_nao_mostra_o_que_nao_esta_aprovado(): void
    {
        $estoquista = User::factory()->estoque()->create();

        foreach (StatusRequisicao::cases() as $status) {
            if ($status === StatusRequisicao::APROVADA) {
                continue;
            }

            Requisicao::factory()->status($status)->create();
        }

        $this->assertSame([], $this->fila->para($estoquista)->pluck('numero')->all());
    }
}
