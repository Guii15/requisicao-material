<?php

namespace Tests\Feature\Services;

use App\Enums\AcaoEvento;
use App\Enums\EtapaAssinatura;
use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Requisicao;
use App\Models\Setor;
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

    public function test_lider_do_estoque_aprova_requisicao_de_setor_sem_aprovador(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $erica = User::factory()->for($rh)->responsavelBaixa()->create();
        $liderEstoque = User::factory()->liderEstoque()->create();
        $requisicao = $this->abrirRequisicao($erica);

        $this->workflow->aprovar($requisicao, $liderEstoque, 'password');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->status);
        $this->assertSame($liderEstoque->id, $requisicao->aprovado_por_id);
    }

    public function test_admin_nao_aprova_nem_setor_sem_aprovador(): void
    {
        // Decisão de 24/09/2026: por enquanto o Admin fica fora da aprovação.
        $rh = Setor::factory()->create(['nome' => 'RH']);
        $admin = User::factory()->admin()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($rh)->create());

        $this->expectException(AuthorizationException::class);

        $this->workflow->aprovar($requisicao, $admin, 'password');
    }

    public function test_lider_do_estoque_nao_aprova_setor_que_tem_aprovador(): void
    {
        [$ti] = $this->setorComAprovadores();
        $liderEstoque = User::factory()->liderEstoque()->create();
        $requisicao = $this->abrirRequisicao(User::factory()->for($ti)->create());

        $this->expectException(AuthorizationException::class);

        $this->workflow->aprovar($requisicao, $liderEstoque, 'password');
    }

    public function test_pedido_do_unico_lider_e_aprovado_pelo_lider_do_estoque(): void
    {
        // Ninja Place: o Gustavo é o único aprovador. O pedido dele vai para os líderes do estoque.
        $ninja = Setor::factory()->create(['nome' => 'NINJA PLACE', 'sem_sublider_definido' => true]);
        $gustavo = User::factory()->for($ninja)->aprovadorDe($ninja)->create();
        $liderEstoque = User::factory()->liderEstoque()->create();
        $requisicao = $this->abrirRequisicao($gustavo);

        $this->workflow->aprovar($requisicao, $liderEstoque, 'password');

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

    // ---- Separação -------------------------------------------------------------------------

    /**
     * Requisição aprovada, pronta pra separar: 2 itens (qtd 1 e qtd 2), como em dadosRequisicao().
     */
    private function requisicaoAprovada(?TipoRequisicao $tipo = null): Requisicao
    {
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao(
            User::factory()->for($ti)->create(),
            $tipo === TipoRequisicao::USO_CONSUMO ? ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null] : [],
        );
        $this->workflow->aprovar($requisicao, $lider, 'password');

        return $requisicao->fresh();
    }

    /**
     * @return array<int, string>
     */
    private function quantidadesSeparadas(Requisicao $requisicao, array $sobrescrever = []): array
    {
        // Collection::merge() renumera chave inteira (igual array_merge); usa "+" pra manter o id do item como chave.
        return $sobrescrever + $requisicao->itens->pluck('qtd_solicitada', 'id')->map(fn ($qtd) => (string) $qtd)->all();
    }

    public function test_estoque_separa_teste_e_avanca_direto_pra_pronta_para_retirada(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();

        $this->workflow->separar($requisicao, $estoquista, $this->quantidadesSeparadas($requisicao), 'password');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::PRONTA_PARA_RETIRADA, $requisicao->status);
        $this->assertSame($estoquista->id, $requisicao->separado_por_id);
        $this->assertNotNull($requisicao->separado_em);
        $this->assertSame(
            [EtapaAssinatura::SOLICITACAO, EtapaAssinatura::APROVACAO_SETOR, EtapaAssinatura::SEPARACAO],
            $requisicao->assinaturas->pluck('etapa')->all(),
        );
        $this->assertSame(
            [AcaoEvento::CRIACAO, AcaoEvento::APROVACAO, AcaoEvento::SEPARACAO, AcaoEvento::AVANCO_AUTOMATICO],
            $requisicao->eventos->pluck('acao')->all(),
        );
    }

    public function test_estoque_separa_uso_e_consumo_e_avanca_pra_aguardando_liberacao(): void
    {
        $requisicao = $this->requisicaoAprovada(TipoRequisicao::USO_CONSUMO);
        $estoquista = User::factory()->estoque()->create();

        $this->workflow->separar($requisicao, $estoquista, $this->quantidadesSeparadas($requisicao), 'password');

        $this->assertSame(StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE, $requisicao->fresh()->status);
    }

    public function test_separar_grava_a_quantidade_separada_de_cada_item(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();
        $itens = $requisicao->itens;

        $this->workflow->separar($requisicao, $estoquista, $this->quantidadesSeparadas($requisicao, [
            $itens[1]->id => '1', // só achou 1 dos 2 pedidos
        ]), 'password');

        $this->assertSame('1.000', $itens[0]->fresh()->qtd_separada);
        $this->assertSame('1.000', $itens[1]->fresh()->qtd_separada);
    }

    public function test_separar_com_quantidade_maior_que_a_solicitada_e_recusado(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();
        $item = $requisicao->itens->first();

        $erros = $this->erroDeValidacao(fn () => $this->workflow->separar(
            $requisicao, $estoquista, $this->quantidadesSeparadas($requisicao, [$item->id => '999']), 'password',
        ));

        $this->assertArrayHasKey("itens.{$item->id}", $erros);
        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    public function test_separar_com_quantidade_negativa_e_recusado(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();
        $item = $requisicao->itens->first();

        $erros = $this->erroDeValidacao(fn () => $this->workflow->separar(
            $requisicao, $estoquista, $this->quantidadesSeparadas($requisicao, [$item->id => '-1']), 'password',
        ));

        $this->assertArrayHasKey("itens.{$item->id}", $erros);
    }

    public function test_quem_nao_e_do_estoque_nao_separa(): void
    {
        $requisicao = $this->requisicaoAprovada();

        $this->expectException(AuthorizationException::class);

        $this->workflow->separar($requisicao, User::factory()->create(), $this->quantidadesSeparadas($requisicao), 'password');
    }

    public function test_requisicao_aguardando_aprovacao_nao_pode_ser_separada(): void
    {
        $solicitante = User::factory()->create();
        $requisicao = $this->abrirRequisicao($solicitante);
        $estoquista = User::factory()->estoque()->create();

        $this->expectException(RegraDeNegocioException::class);

        $this->workflow->separar($requisicao, $estoquista, $this->quantidadesSeparadas($requisicao), 'password');
    }


    // ---- Cenários prontos pra liberação / entrega / recebimento / devolução / baixa --------

    private function requisicaoSeparada(?TipoRequisicao $tipo = null, ?User $estoquista = null): Requisicao
    {
        $requisicao = $this->requisicaoAprovada($tipo);
        $this->workflow->separar($requisicao, $estoquista ?? User::factory()->estoque()->create(), $this->quantidadesSeparadas($requisicao), 'password');

        return $requisicao->fresh();
    }

    /** Uso e Consumo, pronta pra alguém do estoque liberar. */
    private function requisicaoAguardandoLiberacao(): Requisicao
    {
        return $this->requisicaoSeparada(TipoRequisicao::USO_CONSUMO);
    }

    /** Teste, pula liberação (só existe pra Uso e Consumo) e já cai pronta pra retirada. */
    private function requisicaoProntaParaRetirada(): Requisicao
    {
        return $this->requisicaoSeparada(TipoRequisicao::TESTE);
    }

    private function liderEstoqueDiferenteDe(User ...$excluidos): User
    {
        do {
            $lider = User::factory()->liderEstoque()->create();
        } while (in_array($lider->id, array_map(fn (User $u) => $u->id, $excluidos), true));

        return $lider;
    }

    private function requisicaoEmPosse(): Requisicao
    {
        $requisicao = $this->requisicaoProntaParaRetirada();
        $this->workflow->entregar($requisicao, User::factory()->estoque()->create(), 'Fulano de Tal');

        return $requisicao->fresh();
    }

    private function requisicaoAguardandoBaixa(): Requisicao
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();
        $lider = $this->liderEstoqueDiferenteDe(
            User::find($requisicao->aprovado_por_id),
            User::find($requisicao->separado_por_id),
        );
        $this->workflow->liberar($requisicao, $lider, 'password');
        $requisicao->refresh();
        $this->workflow->entregar($requisicao, User::factory()->estoque()->create(), 'Fulano de Tal');

        return $requisicao->fresh();
    }

    // ---- Liberação (só Uso e Consumo) ----------------------------------------------------

    public function test_lider_do_estoque_libera_e_avanca_pra_pronta_para_retirada(): void
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();
        $lider = $this->liderEstoqueDiferenteDe(
            User::find($requisicao->aprovado_por_id),
            User::find($requisicao->separado_por_id),
        );

        $this->workflow->liberar($requisicao, $lider, 'password');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::PRONTA_PARA_RETIRADA, $requisicao->status);
        $this->assertSame($lider->id, $requisicao->liberado_por_id);
        $this->assertNotNull($requisicao->liberado_em);
    }

    public function test_quem_separou_tambem_pode_liberar_a_mesma_requisicao(): void
    {
        $estoquista = User::factory()->liderEstoque()->create();
        $requisicao = $this->requisicaoSeparada(TipoRequisicao::USO_CONSUMO, $estoquista);

        $this->workflow->liberar($requisicao, $estoquista, 'password');

        $this->assertSame($estoquista->id, $requisicao->fresh()->liberado_por_id);
    }

    public function test_teste_nao_passa_por_liberacao(): void
    {
        $requisicao = $this->requisicaoProntaParaRetirada();

        $this->assertSame(StatusRequisicao::PRONTA_PARA_RETIRADA, $requisicao->status);
        $this->assertNull($requisicao->liberado_por_id);
    }

    public function test_lider_do_estoque_reprova_no_estoque_com_motivo(): void
    {
        $requisicao = $this->requisicaoAguardandoLiberacao();
        $lider = $this->liderEstoqueDiferenteDe(
            User::find($requisicao->aprovado_por_id),
            User::find($requisicao->separado_por_id),
        );

        $this->workflow->reprovarEstoque($requisicao, $lider, 'Divergência na conferência: sobrou material no pedido anterior.', 'password');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::REPROVADA_ESTOQUE, $requisicao->status);
        $this->assertSame($lider->id, $requisicao->reprovado_estoque_por_id);
        $this->assertNotNull($requisicao->motivo_reprovacao_estoque);
    }

    // ---- Entrega + Retirada (as duas assinaturas encadeadas) ------------------------------

    public function test_entregar_assina_a_entrega_guarda_quem_retirou_e_avanca_pra_em_posse_no_teste(): void
    {
        $requisicao = $this->requisicaoProntaParaRetirada();
        $estoquista = User::factory()->estoque()->create();

        $this->workflow->entregar($requisicao, $estoquista, 'Maria da Silva (RH, retirando para o setor)');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::EM_POSSE, $requisicao->status);
        $this->assertSame($estoquista->id, $requisicao->entregue_por_id);
        $this->assertSame('Maria da Silva (RH, retirando para o setor)', $requisicao->retirado_por_nome);
        $this->assertNull($requisicao->retirado_por_user_id, 'Quem retira não loga no sistema.');
        $assinaturas = $requisicao->assinaturas->pluck('etapa')->all();
        $this->assertContains(EtapaAssinatura::ENTREGA, $assinaturas);
        $this->assertNotContains(EtapaAssinatura::RETIRADA, $assinaturas, 'Não há mais assinatura desenhada de quem retira.');
    }

    public function test_entregar_no_uso_e_consumo_avanca_pra_aguardando_baixa(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();

        $this->assertSame(StatusRequisicao::AGUARDANDO_BAIXA, $requisicao->status);
    }

    public function test_entregar_sem_nome_de_quem_retira_e_recusado(): void
    {
        $requisicao = $this->requisicaoProntaParaRetirada();

        $erros = $this->erroDeValidacao(fn () => $this->workflow->entregar(
            $requisicao, User::factory()->estoque()->create(), '', 'password',
        ));

        $this->assertArrayHasKey('retirado_por_nome', $erros);
    }

    public function test_quem_nao_e_do_estoque_nao_entrega(): void
    {
        $requisicao = $this->requisicaoProntaParaRetirada();

        $this->expectException(AuthorizationException::class);

        $this->workflow->entregar($requisicao, User::factory()->create(), 'Fulano');
    }

    // ---- Separar e entregar na mesma tela (só Teste) -----------------------------------------

    public function test_separar_e_entregar_no_teste_fecha_as_duas_etapas_de_uma_vez(): void
    {
        $requisicao = $this->requisicaoAprovada();
        $estoquista = User::factory()->estoque()->create();

        $this->workflow->separarEntregar($requisicao, $estoquista, $this->quantidadesSeparadas($requisicao), 'Maria da Silva');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::EM_POSSE, $requisicao->status);
        $this->assertSame($estoquista->id, $requisicao->separado_por_id);
        $this->assertSame($estoquista->id, $requisicao->entregue_por_id);
        $this->assertSame('Maria da Silva', $requisicao->retirado_por_nome);
        $this->assertSame(
            [EtapaAssinatura::SOLICITACAO, EtapaAssinatura::APROVACAO_SETOR, EtapaAssinatura::SEPARACAO, EtapaAssinatura::ENTREGA],
            $requisicao->assinaturas()->orderBy('id')->get()->pluck('etapa')->all(),
        );
        $this->assertTrue(app(\App\Services\AssinaturaService::class)->verificar($requisicao)->integro);
    }

    public function test_separar_e_entregar_sem_nome_de_quem_retira_e_recusado_e_nada_muda(): void
    {
        $requisicao = $this->requisicaoAprovada();

        $erros = $this->erroDeValidacao(fn () => $this->workflow->separarEntregar(
            $requisicao, User::factory()->estoque()->create(), $this->quantidadesSeparadas($requisicao), '',
        ));

        $this->assertArrayHasKey('retirado_por_nome', $erros);
        $this->assertSame(StatusRequisicao::APROVADA, $requisicao->fresh()->status);
    }

    public function test_separar_e_entregar_nao_vale_pro_uso_e_consumo(): void
    {
        $requisicao = $this->requisicaoAprovada(TipoRequisicao::USO_CONSUMO);

        $this->expectException(RegraDeNegocioException::class);

        $this->workflow->separarEntregar($requisicao, User::factory()->estoque()->create(), $this->quantidadesSeparadas($requisicao), 'Fulano');
    }

    public function test_quem_nao_e_do_estoque_nao_separa_e_entrega(): void
    {
        $requisicao = $this->requisicaoAprovada();

        $this->expectException(AuthorizationException::class);

        $this->workflow->separarEntregar($requisicao, User::factory()->create(), $this->quantidadesSeparadas($requisicao), 'Fulano');
    }

    // ---- Devolução (Teste) -----------------------------------------------------------------

    /**
     * @return array<int, array{ok: string, defeito: string, nao_devolvida: string}>
     */
    private function devolucaoCompleta(Requisicao $requisicao, array $sobrescrever = []): array
    {
        return $sobrescrever + $requisicao->itens->mapWithKeys(fn ($item) => [
            $item->id => ['ok' => (string) $item->qtd_solicitada, 'defeito' => '0', 'nao_devolvida' => '0'],
        ])->all();
    }

    public function test_devolucao_completa_e_boa_fecha_como_devolvida(): void
    {
        $requisicao = $this->requisicaoEmPosse();
        $estoquista = User::factory()->estoque()->create();

        $this->workflow->devolver($requisicao, $estoquista, $this->devolucaoCompleta($requisicao), 'Maria da Silva');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::DEVOLVIDA, $requisicao->status);
        $this->assertSame($estoquista->id, $requisicao->devolucao_conferida_por_id);

        $this->assertSame('Maria da Silva', $requisicao->devolvido_por_nome);
        $this->assertSame(
            EtapaAssinatura::DEVOLUCAO,
            $requisicao->assinaturas()->orderBy('id')->get()->last()->etapa,
        );
        $this->assertTrue(app(\App\Services\AssinaturaService::class)->verificar($requisicao)->integro);
    }

    public function test_devolucao_sem_nome_de_quem_devolve_e_recusada(): void
    {
        $requisicao = $this->requisicaoEmPosse();

        $erros = $this->erroDeValidacao(fn () => $this->workflow->devolver(
            $requisicao, User::factory()->estoque()->create(), $this->devolucaoCompleta($requisicao), '',
        ));

        $this->assertArrayHasKey('devolvido_por_nome', $erros);
        $this->assertSame(StatusRequisicao::EM_POSSE, $requisicao->fresh()->status);
    }

    public function test_devolucao_com_item_com_defeito_fica_com_pendencia(): void
    {
        $requisicao = $this->requisicaoEmPosse();
        $item = $requisicao->itens->first();

        $this->workflow->devolver($requisicao, User::factory()->estoque()->create(), $this->devolucaoCompleta($requisicao, [
            $item->id => ['ok' => '0', 'defeito' => (string) $item->qtd_solicitada, 'nao_devolvida' => '0'],
        ]), 'Maria da Silva');

        $this->assertSame(StatusRequisicao::DEVOLUCAO_COM_PENDENCIA, $requisicao->fresh()->status);
    }

    public function test_devolucao_que_nao_fecha_a_soma_e_recusada(): void
    {
        $requisicao = $this->requisicaoEmPosse();
        $item = $requisicao->itens->first();

        $erros = $this->erroDeValidacao(fn () => $this->workflow->devolver($requisicao, User::factory()->estoque()->create(), $this->devolucaoCompleta($requisicao, [
            $item->id => ['ok' => '0', 'defeito' => '0', 'nao_devolvida' => '0'],
        ]), 'Maria da Silva'));

        $this->assertNotEmpty($erros);
        $this->assertSame(StatusRequisicao::EM_POSSE, $requisicao->fresh()->status);
    }

    // ---- Baixa (Uso e Consumo) --------------------------------------------------------------

    public function test_responsavel_pela_baixa_da_baixa(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();
        $erica = User::factory()->responsavelBaixa()->create();

        $this->workflow->darBaixa($requisicao, $erica, 'NF-88412', 'Conferido com o WinThor.', 'password');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::BAIXADA, $requisicao->status);
        $this->assertSame($erica->id, $requisicao->baixa_por_id);
        $this->assertSame('NF-88412', $requisicao->baixa_documento_winthor);
    }

    public function test_responsavel_pela_baixa_nao_da_baixa_no_proprio_pedido(): void
    {
        $rh = Setor::factory()->create(['nome' => 'RH-BAIXA']);
        $erica = User::factory()->for($rh)->responsavelBaixa()->create();
        [$ti, $lider] = $this->setorComAprovadores();
        $requisicao = $this->abrirRequisicao($erica, ['tipo' => 'USO_CONSUMO', 'data_prevista_devolucao' => null]);
        // Erica não tem aprovador no próprio setor: cai pro líder do estoque, igual RH de verdade.
        $liderEstoque = User::factory()->liderEstoque()->create();
        $this->workflow->aprovar($requisicao, $liderEstoque, 'password');
        $this->workflow->separar($requisicao->fresh(), User::factory()->estoque()->create(), $this->quantidadesSeparadas($requisicao->fresh()), 'password');
        $requisicao->refresh();
        $outroLider = $this->liderEstoqueDiferenteDe(User::find($requisicao->separado_por_id));
        $this->workflow->liberar($requisicao, $outroLider, 'password');
        $this->workflow->entregar($requisicao->fresh(), User::factory()->estoque()->create(), 'Erica');
        $requisicao->refresh();

        $this->expectException(AuthorizationException::class);

        $this->workflow->darBaixa($requisicao, $erica, 'NF-1', null, 'password');
    }

    public function test_baixa_sem_documento_winthor_e_recusada(): void
    {
        $requisicao = $this->requisicaoAguardandoBaixa();
        $erica = User::factory()->responsavelBaixa()->create();

        $erros = $this->erroDeValidacao(fn () => $this->workflow->darBaixa($requisicao, $erica, '', null, 'password'));

        $this->assertArrayHasKey('documento', $erros);
    }
}
