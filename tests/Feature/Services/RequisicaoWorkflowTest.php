<?php

namespace Tests\Feature\Services;

use App\Enums\AcaoEvento;
use App\Enums\EtapaAssinatura;
use App\Enums\StatusRequisicao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\TentativaAssinatura;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

class RequisicaoWorkflowTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private RequisicaoWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-24 10:00:00');
        $this->workflow = app(RequisicaoWorkflow::class);
    }

    private function erroDeValidacao(callable $acao): array
    {
        try {
            $acao();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Era esperada uma ValidationException.');
    }

    // ---- Abertura -------------------------------------------------------------------------

    public function test_abertura_grava_requisicao_itens_uma_assinatura_e_um_evento(): void
    {
        [$ti] = $this->setorComAprovadores();
        $solicitante = User::factory()->for($ti)->create();

        $requisicao = $this->abrirRequisicao($solicitante)->refresh();

        $this->assertSame('REQ-2026-000001', $requisicao->numero);
        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, $requisicao->status);
        $this->assertSame($ti->id, $requisicao->setor_id);
        $this->assertSame('2026-09-28', $requisicao->data_prevista_devolucao->toDateString());
        $this->assertCount(2, $requisicao->itens);
        $this->assertSame('2.000', $requisicao->itens[1]->qtd_solicitada);
        $this->assertSame([EtapaAssinatura::SOLICITACAO], $requisicao->assinaturas->pluck('etapa')->all());
        $this->assertSame([AcaoEvento::CRIACAO], $requisicao->eventos->pluck('acao')->all());
    }

    public function test_abertura_com_senha_errada_nao_grava_nada_e_registra_a_tentativa(): void
    {
        $solicitante = User::factory()->create();

        $erros = $this->erroDeValidacao(fn () => $this->workflow->criar($solicitante, $this->dadosRequisicao(), 'senha-errada'));

        $this->assertArrayHasKey('senha', $erros);
        $this->assertSame(0, Requisicao::count());
        $this->assertSame(1, TentativaAssinatura::where('user_id', $solicitante->id)->count());
    }

    public function test_teste_sem_data_de_devolucao_e_recusado(): void
    {
        $erros = $this->erroDeValidacao(fn () => $this->abrirRequisicao(User::factory()->create(), ['data_prevista_devolucao' => null]));

        $this->assertArrayHasKey('data_prevista_devolucao', $erros);
    }

    public function test_devolucao_depois_de_tres_dias_uteis_e_recusada(): void
    {
        // Quinta 24/09 + 3 dias úteis = terça 29/09. Quarta 30/09 passa do prazo.
        $erros = $this->erroDeValidacao(fn () => $this->abrirRequisicao(User::factory()->create(), ['data_prevista_devolucao' => '2026-09-30']));

        $this->assertStringContainsString('29/09/2026', $erros['data_prevista_devolucao'][0]);
    }

    public function test_devolucao_no_ultimo_dia_util_do_prazo_e_aceita(): void
    {
        $requisicao = $this->abrirRequisicao(User::factory()->create(), ['data_prevista_devolucao' => '2026-09-29']);

        $this->assertSame('2026-09-29', $requisicao->data_prevista_devolucao->toDateString());
    }

    public function test_devolucao_no_passado_e_recusada(): void
    {
        $erros = $this->erroDeValidacao(fn () => $this->abrirRequisicao(User::factory()->create(), ['data_prevista_devolucao' => '2026-09-23']));

        $this->assertArrayHasKey('data_prevista_devolucao', $erros);
    }

    public function test_uso_e_consumo_nao_guarda_data_de_devolucao(): void
    {
        $requisicao = $this->abrirRequisicao(User::factory()->create(), ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => '2026-09-28']);

        $this->assertNull($requisicao->fresh()->data_prevista_devolucao);
    }

    public function test_requisicao_sem_itens_e_recusada(): void
    {
        $erros = $this->erroDeValidacao(fn () => $this->abrirRequisicao(User::factory()->create(), ['itens' => []]));

        $this->assertArrayHasKey('itens', $erros);
    }

    public function test_quantidade_com_virgula_e_aceita(): void
    {
        $requisicao = $this->abrirRequisicao(User::factory()->create(), [
            'itens' => [['descricao' => 'Cabo de rede CAT6', 'unidade' => 'm', 'quantidade' => '2,5']],
        ]);

        $item = $requisicao->itens()->first();
        $this->assertSame('2.500', $item->qtd_solicitada);
        $this->assertSame('M', $item->unidade);
    }

    public function test_quantidade_zero_e_recusada(): void
    {
        $erros = $this->erroDeValidacao(fn () => $this->abrirRequisicao(User::factory()->create(), [
            'itens' => [['descricao' => 'Mouse USB', 'unidade' => 'UN', 'quantidade' => '0']],
        ]));

        $this->assertArrayHasKey('itens.0.quantidade', $erros);
    }

    // ---- Aprovação do setor ------------------------------------------------------------------

    public function test_lider_do_setor_aprova(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->workflow->aprovar($requisicao, $lider, 'password');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->status);
        $this->assertSame($lider->id, $requisicao->aprovado_por_id);
        $this->assertNotNull($requisicao->aprovado_em);
        $this->assertSame([EtapaAssinatura::SOLICITACAO, EtapaAssinatura::APROVACAO_SETOR], $requisicao->assinaturas->pluck('etapa')->all());
        $this->assertSame([AcaoEvento::CRIACAO, AcaoEvento::APROVACAO], $requisicao->eventos->pluck('acao')->all());
    }

    public function test_sublider_aprova(): void
    {
        [$ti, , $sublider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->workflow->aprovar($requisicao, $sublider, 'password');

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    public function test_solicitante_nao_aprova_a_propria_requisicao_nem_sendo_lider(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao($lider);

        $this->expectException(AuthorizationException::class);

        $this->workflow->aprovar($requisicao, $lider, 'password');
    }

    public function test_lider_de_outro_setor_nao_aprova(): void
    {
        [$ti] = $this->setorComAprovadores('TI');
        [, $liderFinanceiro] = $this->setorComAprovadores('FINANCEIRO');
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->expectException(AuthorizationException::class);

        $this->workflow->aprovar($requisicao, $liderFinanceiro, 'password');
    }

    public function test_segundo_aprovador_recebe_erro_dizendo_quem_ja_aprovou_e_quando(): void
    {
        [$ti, $lider, $sublider] = $this->setorComAprovadores();
        $lider->forceFill(['nome' => 'Matheus Rodrigues Luiz'])->save();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $this->travelTo('2026-09-24 14:32:00');

        // O sublíder abriu a tela antes da aprovação: o model dele ainda está "aguardando".
        $telaDoSublider = Requisicao::find($requisicao->id);
        $this->workflow->aprovar($requisicao, $lider, 'password');

        try {
            $this->workflow->aprovar($telaDoSublider, $sublider, 'password');
            $this->fail('O segundo aprovador deveria receber erro.');
        } catch (RegraDeNegocioException $e) {
            $this->assertStringContainsString('Aprovada', $e->getMessage());
            $this->assertStringContainsString('Matheus Rodrigues Luiz', $e->getMessage());
            $this->assertStringContainsString('14:32', $e->getMessage());
        }

        $this->assertSame(2, $requisicao->assinaturas()->count());
    }

    public function test_aprovacao_com_senha_errada_nao_muda_nada(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->erroDeValidacao(fn () => $this->workflow->aprovar($requisicao, $lider, 'errada'));

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, $requisicao->status);
        $this->assertNull($requisicao->aprovado_por_id);
        $this->assertSame(1, $requisicao->assinaturas()->count());
        $this->assertSame(1, TentativaAssinatura::where('requisicao_id', $requisicao->id)->count());
    }

    public function test_reprovacao_sem_motivo_e_recusada(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $erros = $this->erroDeValidacao(fn () => $this->workflow->reprovar($requisicao, $lider, '  ', 'password'));

        $this->assertArrayHasKey('motivo', $erros);
        $this->assertSame(StatusRequisicao::AGUARDANDO_APROVACAO, $requisicao->fresh()->status);
    }

    public function test_reprovacao_grava_motivo_e_quem_reprovou(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->workflow->reprovar($requisicao, $lider, 'Já temos placa de teste na bancada.', 'password');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::REPROVADA, $requisicao->status);
        $this->assertSame($lider->id, $requisicao->reprovado_por_id);
        $this->assertNull($requisicao->aprovado_por_id);
        $this->assertSame('Já temos placa de teste na bancada.', $requisicao->motivo_reprovacao);
        $this->assertSame(EtapaAssinatura::REPROVACAO_SETOR, $requisicao->assinaturas->last()->etapa);
    }

    public function test_admin_aprova_requisicao_de_setor_sem_aprovador(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $erica = User::factory()->for($rh)->responsavelBaixa()->create();
        $admin = User::factory()->admin()->create();
        $requisicao = $this->abrirRequisicao($erica);

        $this->workflow->aprovar($requisicao, $admin, 'password');

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    public function test_admin_nao_aprova_setor_que_tem_aprovador(): void
    {
        [$ti] = $this->setorComAprovadores();
        $admin = User::factory()->admin()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->expectException(AuthorizationException::class);

        $this->workflow->aprovar($requisicao, $admin, 'password');
    }

    public function test_pedido_do_unico_lider_e_aprovado_pelo_admin(): void
    {
        // Ninja Place: o Gustavo é o único aprovador. O pedido dele vai para o Admin.
        $ninja = Setor::factory()->create(['nome' => 'NINJA PLACE', 'sem_sublider_definido' => true]);
        $gustavo = User::factory()->for($ninja)->aprovadorDe($ninja)->create();
        $admin = User::factory()->admin()->create();
        $requisicao = $this->abrirRequisicao($gustavo);

        $this->workflow->aprovar($requisicao, $admin, 'password');

        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    // ---- Cancelamento -------------------------------------------------------------------------

    public function test_solicitante_cancela_enquanto_aguarda_aprovacao(): void
    {
        $solicitante = User::factory()->create();
        $requisicao = $this->abrirRequisicao($solicitante);

        $this->workflow->cancelar($requisicao, $solicitante, 'Pedi o item errado.');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::CANCELADA, $requisicao->status);
        $this->assertSame($solicitante->id, $requisicao->cancelado_por_id);
        $this->assertSame('Pedi o item errado.', $requisicao->motivo_cancelamento);
        $this->assertSame(1, $requisicao->assinaturas()->count(), 'Cancelamento não exige assinatura.');
    }

    public function test_cancelamento_sem_motivo_e_recusado(): void
    {
        $solicitante = User::factory()->create();
        $requisicao = $this->abrirRequisicao($solicitante);

        $erros = $this->erroDeValidacao(fn () => $this->workflow->cancelar($requisicao, $solicitante, ''));

        $this->assertArrayHasKey('motivo', $erros);
    }

    public function test_solicitante_nao_cancela_depois_de_aprovada(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $solicitante = User::factory()->for($ti)->create();
        $requisicao = $this->abrirRequisicao($solicitante);
        $this->workflow->aprovar($requisicao, $lider, 'password');

        $this->expectException(AuthorizationException::class);

        $this->workflow->cancelar($requisicao->fresh(), $solicitante, 'Não preciso mais.');
    }

    public function test_admin_cancela_requisicao_aprovada(): void
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $admin = User::factory()->admin()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $this->workflow->aprovar($requisicao, $lider, 'password');

        $this->workflow->cancelar($requisicao->fresh(), $admin, 'Material reservado para outro cliente.');

        $this->assertSame(StatusRequisicao::CANCELADA, $requisicao->fresh()->status);
    }

    public function test_requisicao_reprovada_nao_pode_ser_aprovada(): void
    {
        [$ti, $lider, $sublider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());
        $this->workflow->reprovar($requisicao, $lider, 'Sem justificativa suficiente.', 'password');

        $this->expectException(RegraDeNegocioException::class);

        $this->workflow->aprovar($requisicao->fresh(), $sublider, 'password');
    }
}
