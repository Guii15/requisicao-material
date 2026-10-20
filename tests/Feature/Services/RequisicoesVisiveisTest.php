<?php

namespace Tests\Feature\Services;

use App\Enums\EtapaAssinatura as Etapa;
use App\Enums\StatusRequisicao as S;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\AssinaturaService;
use App\Services\RequisicoesVisiveis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * O filtro em SQL tem que dar exatamente o que a RequisicaoPolicy::view libera.
 */
class RequisicoesVisiveisTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    public function test_filtro_confere_com_a_policy_para_todo_papel_tipo_e_status(): void
    {
        $this->travelTo('2026-09-24 10:00:00');

        [$setor, $lider] = $this->setorComAprovadores();
        $semAprovador = Setor::factory()->create(['nome' => 'Sem aprovador']);
        $solicitante = User::factory()->for($setor)->create();
        $orfao = User::factory()->for($semAprovador)->create();

        foreach ([$solicitante, $orfao] as $quem) {
            foreach (TipoRequisicao::cases() as $tipo) {
                foreach (S::cases() as $status) {
                    Requisicao::factory()->for($quem, 'solicitante')->create([
                        'tipo' => $tipo,
                        'status' => $status,
                        'data_prevista_devolucao' => $tipo === TipoRequisicao::TESTE ? now()->addDay()->toDateString() : null,
                    ]);
                }
            }
        }

        // Quem assinou uma etapa continua vendo, mesmo sem papel nenhum.
        $assinante = User::factory()->create();
        $assinada = Requisicao::query()->where('tipo', TipoRequisicao::COMPRA_FUNCIONARIO)->where('status', S::COMPRA_APROVADA)->firstOrFail();
        app(AssinaturaService::class)->assinarComSenha($assinada, Etapa::COMPRA_APROVADA, $assinante);

        $usuarios = [
            'solicitante' => $solicitante,
            'colega' => User::factory()->for($setor)->create(),
            'aprovador' => $lider,
            'assinante' => $assinante,
            'estoque' => User::factory()->estoque()->create(),
            'lider do estoque' => User::factory()->liderEstoque()->create(),
            'responsavel pela baixa' => User::factory()->responsavelBaixa()->create(),
            'aprova compras' => User::factory()->create(['aprova_compras' => true]),
            'admin' => User::factory()->admin()->create(),
        ];

        $filtro = app(RequisicoesVisiveis::class);
        $todas = Requisicao::all();

        foreach ($usuarios as $papel => $user) {
            $pelaPolicy = $todas->filter(fn (Requisicao $r) => $user->can('view', $r))->pluck('id')->sort()->values()->all();

            $query = Requisicao::query();
            $filtro->aplicar($query, $user);
            $peloFiltro = $query->pluck('id')->sort()->values()->all();

            $this->assertSame($pelaPolicy, $peloFiltro, "papel: {$papel}");
        }

        $this->assertTrue($assinante->can('view', $assinada));
    }
}
