<?php

namespace Database\Seeders;

use App\Enums\PapelSetor;
use App\Models\Requisicao;
use App\Models\Setor;
use App\Models\User;
use App\Services\RequisicaoWorkflow;
use App\Support\DiasUteis;
use Illuminate\Database\Seeder;

/**
 * Dados para ver o sistema funcionando na máquina de desenvolvimento.
 * Usuários com nome de papel (e não de pessoa) para não confundir com a carga real.
 * Nunca roda em produção.
 */
class DemonstracaoSeeder extends Seeder
{
    public const SENHA = 'demo1234';

    public function run(RequisicaoWorkflow $workflow): void
    {
        if (app()->isProduction()) {
            $this->command?->error('A demonstração não roda em produção.');

            return;
        }

        $setores = collect(['ESTOQUE', 'ENTRADA', 'SHOWROOM', 'NINJA PLACE', 'FINANCEIRO', 'TI', 'RH'])
            ->mapWithKeys(fn (string $nome) => [$nome => Setor::firstOrCreate(
                ['nome' => $nome],
                ['sem_sublider_definido' => in_array($nome, ['NINJA PLACE', 'RH'], true)],
            )]);

        $usuario = function (string $login, string $nome, string $cargo, string $setor, array $papeis = []) use ($setores): User {
            $user = User::firstOrNew(['login' => $login]);
            $user->fill(['nome' => $nome, 'cargo' => $cargo, 'setor_id' => $setores[$setor]->id, 'password' => self::SENHA]);
            $user->forceFill(['ativo' => true, 'deve_trocar_senha' => false, ...$papeis])->save();

            return $user;
        };

        $usuario('demo.admin', 'Admin (demonstração)', 'Auxiliar de TI', 'TI', ['is_admin' => true]);
        $liderTi = $usuario('demo.lider', 'Líder da TI (demonstração)', 'Analista de Sistemas', 'TI');
        $subliderTi = $usuario('demo.sublider', 'Sublíder da TI (demonstração)', 'Auxiliar de TI', 'TI');
        $solicitante = $usuario('demo.solicitante', 'Solicitante da TI (demonstração)', 'Auxiliar de TI', 'TI');
        $liderEstoque = $usuario('demo.liderestoque', 'Líder do Estoque (demonstração)', 'Supervisor de Estoque', 'ESTOQUE', ['is_estoque' => true, 'is_lider_estoque' => true]);
        $usuario('demo.estoque', 'Estoquista (demonstração)', 'Auxiliar de Estoque', 'ESTOQUE', ['is_estoque' => true]);
        $showroom = $usuario('demo.showroom', 'Vendedor do Showroom (demonstração)', 'Vendedor', 'SHOWROOM');
        $baixa = $usuario('demo.baixa', 'Responsável pela baixa (demonstração)', 'Assistente de RH', 'RH', ['is_responsavel_baixa' => true]);

        $setores['TI']->aprovadores()->syncWithoutDetaching([
            $liderTi->id => ['papel' => PapelSetor::LIDER],
            $subliderTi->id => ['papel' => PapelSetor::SUBLIDER],
        ]);
        // Os líderes do estoque aprovam o Estoque e o Showroom (decisão de 23/09/2026).
        $setores['ESTOQUE']->aprovadores()->syncWithoutDetaching([$liderEstoque->id => ['papel' => PapelSetor::LIDER]]);
        $setores['SHOWROOM']->aprovadores()->syncWithoutDetaching([$liderEstoque->id => ['papel' => PapelSetor::LIDER]]);

        if (Requisicao::query()->exists()) {
            return;
        }

        $devolucao = DiasUteis::doBanco()->somar(now(), 2)->toDateString();
        $abrir = fn (User $quem, array $dados) => $workflow->criar($quem, $dados, self::SENHA);

        $abrir($solicitante, [
            'tipo' => 'TESTE',
            'itens' => [
                ['descricao' => 'Placa de vídeo RTX 4060 8GB', 'unidade' => 'UN', 'quantidade' => '1'],
                ['descricao' => 'Fonte 650W 80 Plus Bronze', 'unidade' => 'UN', 'quantidade' => '1'],
            ],
            'justificativa' => 'O cliente relatou tela preta e precisamos confirmar se o problema é a placa.',
            'finalidade' => 'Testar a placa no PC do pedido 88412 antes da entrega.',
            'data_prevista_devolucao' => $devolucao,
        ]);

        $abrir($solicitante, [
            'tipo' => 'USO_CONSUMO',
            'itens' => [
                ['descricao' => 'Pasta térmica Arctic MX-4 4g', 'unidade' => 'UN', 'quantidade' => '2'],
                ['descricao' => 'Álcool isopropílico 1L', 'unidade' => 'L', 'quantidade' => '1'],
            ],
            'justificativa' => 'Estoque da bancada da TI acabou.',
            'finalidade' => 'Manutenção preventiva dos computadores do financeiro.',
        ]);

        $abrir($showroom, [
            'tipo' => 'USO_CONSUMO',
            'itens' => [['descricao' => 'Cabo HDMI 2.0 2m', 'unidade' => 'UN', 'quantidade' => '3']],
            'justificativa' => 'Os cabos da vitrine estão com mau contato.',
            'finalidade' => 'Ligar os monitores de exposição do showroom.',
        ]);

        $abrir($baixa, [
            'tipo' => 'TESTE',
            'itens' => [['descricao' => 'Headset USB com microfone', 'unidade' => 'UN', 'quantidade' => '1']],
            'justificativa' => 'O headset atual está falhando nas chamadas.',
            'finalidade' => 'Testar nas entrevistas online desta semana.',
            'data_prevista_devolucao' => $devolucao,
        ]);

        $aprovada = $abrir($subliderTi, [
            'tipo' => 'TESTE',
            'itens' => [['descricao' => 'SSD NVMe 1TB Kingston NV2', 'unidade' => 'UN', 'quantidade' => '1']],
            'justificativa' => 'Comparar desempenho com o SSD que está no PC de testes.',
            'finalidade' => 'Teste de desempenho para o orçamento do cliente 2291.',
            'data_prevista_devolucao' => $devolucao,
        ]);
        $workflow->aprovar($aprovada, $liderTi, self::SENHA);

        $reprovada = $abrir($solicitante, [
            'tipo' => 'USO_CONSUMO',
            'itens' => [['descricao' => 'Mouse sem fio Logitech M280', 'unidade' => 'UN', 'quantidade' => '1']],
            'justificativa' => 'O mouse da minha mesa quebrou.',
            'finalidade' => 'Uso na estação de trabalho da TI.',
        ]);
        $workflow->reprovar($reprovada, $subliderTi, 'Tem mouse reserva no armário da TI. Pegue lá.', self::SENHA);
    }
}
