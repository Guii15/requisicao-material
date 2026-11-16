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
        $this->kelber = User::factory()->liderEstoque()->create();
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
            'filial_destino' => 'BINARIO',
            'foto' => UploadedFile::fake()->image('produto.jpg', 800, 600),
        ], $sobrescrever));
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
            ->assertSee('Para qual filial vai')
            ->assertSee('Binário Showroom')
            ->assertSee('Foto do produto')
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_abrir_sem_filial_ou_sem_foto_volta_com_erro(): void
    {
        $this->actingAs($this->vendedor)
            ->post('/requisicoes', $this->dadosTransferencia(['filial_destino' => null, 'foto' => null]))
            ->assertSessionHasErrors(['filial_destino', 'foto']);

        $this->actingAs($this->vendedor)
            ->post('/requisicoes', $this->dadosTransferencia(['filial_destino' => 'MATRIZ']))
            ->assertSessionHasErrors('filial_destino');

        $this->actingAs($this->vendedor)
            ->post('/requisicoes', $this->dadosTransferencia(['foto' => UploadedFile::fake()->create('nota.pdf', 10, 'application/pdf')]))
            ->assertSessionHasErrors('foto');

        $this->assertSame(0, Requisicao::query()->count());
    }

    public function test_abrir_grava_filial_e_foto_sem_setor_de_destino(): void
    {
        $requisicao = $this->abrirPelaTela();

        $this->assertSame(TipoRequisicao::TRANSFERENCIA_FILIAL, $requisicao->tipo);
        $this->assertSame(Filial::BINARIO, $requisicao->filial_destino);
        $this->assertNull($requisicao->setor_destino_id);
        $this->assertNull($requisicao->data_prevista_devolucao);
        $this->assertNotNull($requisicao->foto_produto);
        Storage::disk('local')->assertExists($requisicao->foto_produto);

        // A foto só abre para quem pode ver a requisição.
        $this->actingAs($this->vendedor)->get("/requisicoes/{$requisicao->numero}/foto-produto")->assertOk();
        $this->actingAs(User::factory()->create())->get("/requisicoes/{$requisicao->numero}/foto-produto")->assertForbidden();
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
        $this->assertSame(StatusRequisicao::AGUARDANDO_RECEBIMENTO, $requisicao->status);
        $this->assertSame($this->isabelly->id, $requisicao->transferido_por_id);
        $this->assertSame('NF de transferência 5531', $requisicao->transferencia_observacao);

        // Depois cai para os líderes do estoque receberem.
        $this->actingAs($this->kelber)->get('/recebimento')->assertOk()->assertSee($requisicao->numero);
        $this->actingAs($this->kelber)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Confirmar recebimento')
            ->assertSee('Recusar recebimento')
            ->assertSee('NF de transferência 5531');
        $this->actingAs(User::factory()->estoque()->create())->post("/requisicoes/{$requisicao->numero}/receber")->assertForbidden();

        $this->actingAs($this->kelber)->post("/requisicoes/{$requisicao->numero}/receber")->assertRedirect('/recebimento');
        $requisicao->refresh();
        $this->assertSame(StatusRequisicao::TRANSFERENCIA_RECEBIDA, $requisicao->status);
        $this->assertSame($this->kelber->id, $requisicao->recebido_por_id);
        $this->assertTrue($requisicao->status->isFinal());

        $this->assertSame(
            [EtapaAssinatura::SOLICITACAO, EtapaAssinatura::APROVACAO_SETOR, EtapaAssinatura::TRANSFERENCIA, EtapaAssinatura::RECEBIMENTO],
            $requisicao->assinaturas()->get()->pluck('etapa')->all(),
        );
        $this->assertTrue(app(AssinaturaService::class)->verificar($requisicao)->integro);

        // O solicitante acompanha até o fim, e o PDF sai.
        $this->actingAs($this->vendedor)->get("/requisicoes/{$requisicao->numero}")
            ->assertOk()
            ->assertSee('Recebida na filial')
            ->assertSee('Recebimento confirmado');
        $this->actingAs($this->vendedor)->get("/requisicoes/{$requisicao->numero}/pdf")->assertOk();
    }

    public function test_lider_do_estoque_recusa_o_recebimento_com_motivo(): void
    {
        $requisicao = $this->abrirPelaTela();
        $this->actingAs($this->ana)->post("/requisicoes/{$requisicao->numero}/aprovar");
        $this->actingAs($this->isabelly)->post("/requisicoes/{$requisicao->numero}/transferir");

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
