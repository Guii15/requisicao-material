<?php

namespace Tests\Feature\Services;

use App\Enums\StatusRequisicao;
use App\Models\Requisicao;
use App\Models\User;
use App\Services\FilaDeBaixa;
use App\Services\FilaDeDevolucao;
use App\Services\FilaDeEntrega;
use App\Services\FilaDeLiberacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * As filas de liberação, entrega, devolução e baixa. A de separação já tem teste próprio
 * (FilaDeSeparacaoTest) por ter sido a primeira; as outras seguem a mesma lógica.
 */
class FilasDeEstoqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_fila_de_liberacao_exclui_quem_aprovou_ou_separou(): void
    {
        $aprovador = User::factory()->create();
        $separador = User::factory()->create();
        $requisicao = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::AGUARDANDO_LIBERACAO_ESTOQUE)->create([
            'aprovado_por_id' => $aprovador->id,
            'separado_por_id' => $separador->id,
        ]);
        $fila = app(FilaDeLiberacao::class);

        $this->assertContains($requisicao->numero, $fila->para(User::factory()->liderEstoque()->create())->pluck('numero')->all());
        $this->assertNotContains($requisicao->numero, $fila->para($aprovador)->pluck('numero')->all());
        $this->assertNotContains($requisicao->numero, $fila->para($separador)->pluck('numero')->all());
    }

    public function test_fila_de_entrega_mostra_prontas_para_retirada(): void
    {
        $pronta = Requisicao::factory()->status(StatusRequisicao::PRONTA_PARA_RETIRADA)->create();
        Requisicao::factory()->status(StatusRequisicao::APROVADA)->create();

        $numeros = app(FilaDeEntrega::class)->para(User::factory()->estoque()->create())->pluck('numero')->all();

        $this->assertSame([$pronta->numero], $numeros);
    }

    public function test_fila_de_devolucao_so_mostra_teste_em_posse(): void
    {
        $emPosse = Requisicao::factory()->status(StatusRequisicao::EM_POSSE)->create();
        Requisicao::factory()->usoConsumo()->status(StatusRequisicao::AGUARDANDO_BAIXA)->create();

        $numeros = app(FilaDeDevolucao::class)->para(User::factory()->estoque()->create())->pluck('numero')->all();

        $this->assertSame([$emPosse->numero], $numeros);
    }

    public function test_fila_de_baixa_exclui_o_proprio_pedido(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $deOutro = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::AGUARDANDO_BAIXA)->create();
        $daPropriaErica = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::AGUARDANDO_BAIXA)->create(['solicitante_id' => $erica->id]);

        $numeros = app(FilaDeBaixa::class)->para($erica)->pluck('numero')->all();

        $this->assertContains($deOutro->numero, $numeros);
        $this->assertNotContains($daPropriaErica->numero, $numeros);
    }

    public function test_fila_de_baixa_do_admin_so_mostra_pedido_da_propria_responsavel(): void
    {
        $erica = User::factory()->responsavelBaixa()->create();
        $admin = User::factory()->admin()->create();
        $daErica = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::AGUARDANDO_BAIXA)->create(['solicitante_id' => $erica->id]);
        $deOutro = Requisicao::factory()->usoConsumo()->status(StatusRequisicao::AGUARDANDO_BAIXA)->create();

        $numeros = app(FilaDeBaixa::class)->para($admin)->pluck('numero')->all();

        $this->assertContains($daErica->numero, $numeros);
        $this->assertNotContains($deOutro->numero, $numeros);
    }
}
