<?php

namespace Tests\Feature\Http;

use App\Enums\EtapaAssinatura;
use App\Enums\Filial;
use App\Enums\PapelSetor;
use App\Enums\StatusRequisicao;
use App\Enums\TipoRequisicao;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\AssinaturaService;
use App\Services\FilaDeAprovacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioRequisicao;
use Tests\TestCase;

/**
 * Transferência de filial: o Showroom pede, o líder do setor aprova, quem faz a transferência
 * (Isabelly) transfere no WinThor e um líder do estoque confirma o recebimento.
 */
class TransferenciaFilialTest extends TestCase
{
    use CenarioRequisicao, RefreshDatabase;

    private Setor $showroom;

    private User $vendedor;

    private User $ana;

    private User $kelber;

    private User $isabelly;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-16 10:00:00');

        Setor::factory()->create(['nome' => 'ESTOQUE']);
        // No Showroom os líderes do estoque aprovam junto (Uso e Consumo); na transferência, não.
        $this->showroom = Setor::factory()->create(['nome' => 'SHOWROOM', 'lideres_estoque_aprovam' => true]);
        $this->vendedor = User::factory()->for($this->showroom)->create();
        $this->ana = User::factory()->for($this->showroom)->aprovadorDe($this->showroom, PapelSetor::LIDER)->create();
        // Como na planilha: os líderes do estoque também estão cadastrados como aprovadores do Showroom.
        $this->kelber = User::factory()->liderEstoque()->aprovadorDe($this->showroom)->create();
        $this->isabelly = User::factory()->for($this->showroom)->fazTransferencia()->create();
    }

    /**
     * @param  array<string, mixed>  $sobrescrever
     * @return array<string, mixed>
     */
    private function dadosTransferencia(array $sobrescrever = []): array
    {
        return $this->dadosRequisicao(array_merge([
            'tipo' => 'TRANSFERENCIA_FILIAL',
            'data_prevista_devolucao' => null,
            'finalidade' => 'Venda do pedido 88412 para o cliente.',
            'filial_origem' => 'SHOWROOM',
            'filial_destino' => 'BINARIO',
        ], $sobrescrever));
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->image('produto.jpg', 800, 600);
    }

    private function abrirPelaTela(): Requisicao
    {
        $this->actingAs($this->vendedor)->post('/requisicoes', $this->dadosTransferencia())->assertSessionHasNoErrors();

        return Requisicao::query()->latest('id')->firstOrFail();
    }

    public function test_formulario_mostra_a_transferencia_com_as_filiais_e_a_foto(): void
    {
        $this->actingAs($this->vendedor)->get('/requisicoes/nova')
            ->assertOk()
            ->assertSee('Transferência de filial')
            ->assertSee('De qual filial sai')
            ->assertSee('Para qual filial vai')
            ->assertSee('Ninja Place')
            ->assertDontSee('Binário Showroom');
    }

    public function test_abrir_sem_filial_de_origem_volta_com_erro(): void
    {
        $this->actingAs($this->vendedor)
            ->post('/requisicoes', $this->dadosTransferencia(['filial_origem' => null, 'filial_destino' => null]))
            ->assertSessionHasErrors(['filial_origem', 'filial_destino']);

        $this->actingAs($this->vendedor)
            ->post('/requisicoes', $this->dadosTransferencia(['filial_origem' => 'BINARIO', 'filial_destino' => 'BINARIO']))
            ->assertSessionHasErrors('filial_destino');

        $this->actingAs($this->vendedor)
            ->post('/requisicoes', $this->dadosTransferencia(['filial_origem' => 'BINARIO_SHOWROOM']))
            ->assertSessionHasErrors('filial_origem');

        $this->assertSame(0, Requisicao::query()->count());
    }

    public function test_abrir_grava_origem_e_destino_sem_setor_de_destino_e_sem_foto(): void
    {
        $requisicao = $this->abrirPelaTela();

        $this->assertSame(TipoRequisicao::TRANSFERENCIA_FILIAL, $requisicao->tipo);
        $this->assertSame(Filial::SHOWROOM, $requisicao->filial_origem);
        $this->assertSame(Filial::BINARIO, $requisicao->filial_destino);
        $this->assertNull($requisicao->setor_destino_id);
        $this->assertNull($requisicao->data_prevista_devolucao);
        $this->assertNull($requisicao->foto_saida);
    }

    public function test_ninja_place_manda_para_o_showroom(): void
    {
        $ninja = Setor::factory()->create(['nome' => 'NINJA PLACE', 'lideres_estoque_aprovam' => true]);
        $gustavo = User::factory()->for($ninja)->aprovadorDe($ninja)->create();
        $solicitante = User::factory()->for($ninja)->create();

        $this->actingAs($solicitante)
            ->post('/requisicoes', $this->dadosTransferencia(['filial_origem' => 'NINJA_PLACE', 'filial_destino' => 'SHOWROOM']))
            ->assertSessionHasNoErrors();
        $requisicao = Requisicao::query()->latest('id')->firstOrFail();

        $this->assertSame(Filial::NINJA_PLACE, $requisicao->filial_origem);
        $this->assertSame(Filial::SHOWROOM, $requisicao->filial_destino);
        $this->assertTrue($gustavo->can('aprovar', $requisicao));
        $this->assertFalse($this->kelber->can('aprovar', $requisicao));
    }

    public function test_binario_tambem_pede_e_o_proprio_lider_aprova(): void
    {
        [$compras, $liderCompras] = $this->setorComAprovadores('COMPRAS');
        $solicitante = User::factory()->for($compras)->create();

        $this->actingAs($solicitante)->post('/requisicoes', $this->dadosTransferencia(['filial_origem' => 'BINARIO', 'filial_destino' => 'SHOWROOM']))->assertSessionHasNoErrors();
        $requisicao = Requisicao::query()->latest('id')->firstOrFail();

        $this->assertSame(Filial::BINARIO, $requisicao->filial_origem);
        $this->assertSame(Filial::SHOWROOM, $requisicao->filial_destino);
        $this->assertTrue($liderCompras->can('aprovar', $requisicao));
        $this->assertFalse($this->ana->can('aprovar', $requisicao));
    }

    public function test_fluxo_completo_lider_do_setor_isabelly_e_estoque_recebe(): void
    {
        $requisicao = $this->abrirPelaTela();

        // Na transferência quem aprova é o líder do setor; o líder do estoque não entra, mesmo no Showroom.
        $this->assertTrue($this->ana->can('aprovar', $requisicao));
        $this->assertFalse($this->kelber->can('aprovar', $requisicao));
        $this->assertSame(0, app(FilaDeAprovacao::class)->para($this->kelber)->count());
        $this->assertSame([$this->ana->id], app(FilaDeAprovacao::class)->aprovadoresElegiveis($requisicao)->pluck('id')->all());

        $this->actingAs($this->ana)->post("/requisicoes/{$requisicao->numero}/aprovar")->assertSessionHasNoErrors();
        $this->assertSame(StatusRequisicao::AGUARDANDO_TRANSFERENCIA, $requisicao->fresh()->status);

        // Cai na fila da Isabelly.
        $this->actingAs($this->isabelly)->get('/transferencias')->assertOk()->assertSee($requisicao->numero);
        $this->actingAs($this->isabelly)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Transferência feita')
            ->assertSee('Binário');
        $this->actingAs($this->kelber)->post("/requisicoes/{$requisicao->numero}/transferir")->assertForbidden();

        $this->actingAs($this->isabelly)
            ->post("/requisicoes/{$requisicao->numero}/transferir", ['observacao' => 'NF de transferência 5531'])
            ->assertRedirect('/transferencias');
        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::AGUARDANDO_ENVIO, $requisicao->status);
        $this->assertSame($this->isabelly->id, $requisicao->transferido_por_id);
        $this->assertSame('NF de transferência 5531', $requisicao->transferencia_observacao);

        // O estoque envia, com a foto da saída (obrigatória).
        $estoquista = User::factory()->estoque()->create();
        $this->actingAs($estoquista)->get('/envio')->assertOk()->assertSee($requisicao->numero);
        $this->actingAs($estoquista)->get("/requisicoes/{$requisicao->numero}")->assertOk()->assertSee('Enviar produto');
        $this->actingAs($this->isabelly)->post("/requisicoes/{$requisicao->numero}/enviar", ['foto' => $this->foto()])->assertForbidden();
        $this->actingAs($estoquista)->post("/requisicoes/{$requisicao->numero}/enviar")->assertSessionHasErrors('foto');
        $this->actingAs($estoquista)
            ->post("/requisicoes/{$requisicao->numero}/enviar", ['foto' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('foto');
        $this->actingAs($estoquista)
            ->post("/requisicoes/{$requisicao->numero}/enviar", ['foto' => $this->foto(), 'observacao' => 'Foi na caixa original'])
            ->assertRedirect('/envio');
        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::AGUARDANDO_RECEBIMENTO, $requisicao->status);
        $this->assertSame($estoquista->id, $requisicao->enviado_por_id);
        Storage::disk('local')->assertExists($requisicao->foto_saida);

        // Depois cai para os líderes do estoque receberem.
        $this->actingAs($this->kelber)->get('/recebimento')->assertOk()->assertSee($requisicao->numero);
        $this->actingAs($this->kelber)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Confirmar recebimento')
            ->assertSee('Recusar recebimento')
            ->assertSee('NF de transferência 5531');
        $this->actingAs($estoquista)->post("/requisicoes/{$requisicao->numero}/receber", ['foto' => $this->foto()])->assertForbidden();
        $this->actingAs($this->kelber)->post("/requisicoes/{$requisicao->numero}/receber")->assertSessionHasErrors('foto');

        $this->actingAs($this->kelber)->post("/requisicoes/{$requisicao->numero}/receber", ['foto' => $this->foto()])->assertRedirect('/recebimento');
        $requisicao->refresh();
        Storage::disk('local')->assertExists($requisicao->foto_chegada);
        $this->assertSame(StatusRequisicao::TRANSFERENCIA_RECEBIDA, $requisicao->status);
        $this->assertSame($this->kelber->id, $requisicao->recebido_por_id);
        $this->assertTrue($requisicao->status->isFinal());

        $this->assertSame(
            [EtapaAssinatura::SOLICITACAO, EtapaAssinatura::APROVACAO_SETOR, EtapaAssinatura::TRANSFERENCIA, EtapaAssinatura::ENVIO, EtapaAssinatura::RECEBIMENTO],
            $requisicao->assinaturas()->get()->pluck('etapa')->all(),
        );
        $this->assertTrue(app(AssinaturaService::class)->verificar($requisicao)->integro);

        // O solicitante acompanha até o fim, e o PDF sai.
        $this->actingAs($this->vendedor)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Recebida na filial')
            ->assertSee('Recebimento confirmado')
            ->assertSee('Foto na saída')
            ->assertSee('Foto na chegada');
        $this->actingAs($this->vendedor)->get("/requisicoes/{$requisicao->numero}/pdf")->assertOk();

        // As fotos só abrem para quem pode ver a requisição.
        $this->actingAs($this->vendedor)->get("/requisicoes/{$requisicao->numero}/foto/saida")->assertOk();
        $this->actingAs($this->vendedor)->get("/requisicoes/{$requisicao->numero}/foto/chegada")->assertOk();
        $this->actingAs(User::factory()->create())->get("/requisicoes/{$requisicao->numero}/foto/saida")->assertForbidden();
    }

    public function test_lider_do_estoque_recusa_o_recebimento_com_motivo(): void
    {
        $requisicao = $this->abrirPelaTela();
        $this->actingAs($this->ana)->post("/requisicoes/{$requisicao->numero}/aprovar");
        $this->actingAs($this->isabelly)->post("/requisicoes/{$requisicao->numero}/transferir");
        $this->actingAs($this->kelber)->post("/requisicoes/{$requisicao->numero}/enviar", ['foto' => $this->foto()]);

        $this->actingAs($this->kelber)
            ->post("/requisicoes/{$requisicao->numero}/recusar-recebimento", ['motivo' => ''])
            ->assertSessionHasErrors('motivo');
        $this->assertSame(StatusRequisicao::AGUARDANDO_RECEBIMENTO, $requisicao->fresh()->status);

        $this->actingAs($this->kelber)
            ->post("/requisicoes/{$requisicao->numero}/recusar-recebimento", ['motivo' => 'Produto chegou com a caixa amassada.'])
            ->assertRedirect('/recebimento');

        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::REPROVADA_ESTOQUE, $requisicao->status);
        $this->assertTrue(app(AssinaturaService::class)->verificar($requisicao)->integro);
        $this->actingAs($this->vendedor)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Recebimento recusado pelo estoque')
            ->assertSee('Produto chegou com a caixa amassada.');
    }

    public function test_estoque_pede_do_binario_e_outro_lider_do_estoque_aprova(): void
    {
        $estoque = Setor::query()->where('nome', 'ESTOQUE')->firstOrFail();
        $this->kelber->forceFill(['setor_id' => $estoque->id])->save();
        $geisla = User::factory()->for($estoque)->liderEstoque()->aprovadorDe($estoque)->create();
        $estoque->aprovadores()->attach($this->kelber->id, ['papel' => PapelSetor::LIDER]);

        $this->actingAs($this->kelber)->post('/requisicoes', $this->dadosTransferencia(['filial_origem' => 'BINARIO', 'filial_destino' => 'SHOWROOM']))->assertSessionHasNoErrors();
        $requisicao = Requisicao::query()->latest('id')->firstOrFail();

        $this->assertTrue($geisla->can('aprovar', $requisicao));
        $this->assertFalse($this->kelber->can('aprovar', $requisicao));
        $this->assertFalse($this->ana->can('aprovar', $requisicao));
        $this->assertSame([$requisicao->id], app(FilaDeAprovacao::class)->para($geisla)->pluck('id')->all());
        $this->assertSame(0, app(FilaDeAprovacao::class)->para($this->ana)->count());
    }

    public function test_fila_de_aprovacao_confere_com_a_policy(): void
    {
        $this->abrirPelaTela();
        $requisicao = Requisicao::query()->latest('id')->firstOrFail();

        foreach ([$this->ana, $this->kelber, $this->isabelly, User::factory()->liderEstoque()->create()] as $user) {
            $this->assertSame(
                $user->can('aprovar', $requisicao),
                app(FilaDeAprovacao::class)->para($user)->whereKey($requisicao->id)->exists(),
                $user->login,
            );
        }
    }

    public function test_setor_sem_aprovador_cai_para_os_lideres_do_estoque(): void
    {
        $semLider = Setor::factory()->create(['nome' => 'TI']);
        $solicitante = User::factory()->for($semLider)->create();

        $this->actingAs($solicitante)->post('/requisicoes', $this->dadosTransferencia())->assertSessionHasNoErrors();
        $requisicao = Requisicao::query()->latest('id')->firstOrFail();

        $this->assertTrue($this->kelber->can('aprovar', $requisicao));
        $this->assertSame(1, app(FilaDeAprovacao::class)->para($this->kelber)->count());
    }

    public function test_quem_nao_faz_transferencia_nao_ve_a_fila(): void
    {
        $this->actingAs($this->vendedor)->get('/transferencias')->assertForbidden();
        $this->actingAs($this->vendedor)->get('/recebimento')->assertForbidden();
        $this->actingAs($this->vendedor)->get('/envio')->assertForbidden();
        $this->actingAs($this->isabelly)->get('/requisicoes')->assertOk()->assertSee('Transferências');
    }

    public function test_admin_da_a_permissao_de_fazer_transferencia(): void
    {
        $admin = User::factory()->admin()->create();
        $pessoa = User::factory()->for($this->showroom)->create();

        $this->actingAs($admin)->put("/usuarios/{$pessoa->id}", [
            'nome' => $pessoa->nome,
            'login' => $pessoa->login,
            'matricula' => $pessoa->matricula,
            'cargo' => $pessoa->cargo,
            'setor_id' => $this->showroom->id,
            'ativo' => '1',
            'faz_transferencia' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($pessoa->fresh()->faz_transferencia);
        $this->actingAs($admin)->get("/usuarios/{$pessoa->id}")->assertOk()->assertSee('Faz transferência entre filiais');
    }

    public function test_admin_cancela_transferencia_esperando_a_isabelly(): void
    {
        $admin = User::factory()->admin()->create();
        $requisicao = $this->abrirPelaTela();
        $this->actingAs($this->ana)->post("/requisicoes/{$requisicao->numero}/aprovar");

        $this->actingAs($admin)
            ->post("/requisicoes/{$requisicao->numero}/cancelar", ['motivo' => 'Venda desfeita pelo cliente.'])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusRequisicao::CANCELADA, $requisicao->fresh()->status);
    }
}
