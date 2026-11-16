<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\ImportacaoUsuarios;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Carrega os funcionários reais direto no banco (lista fechada com o Guilherme em 16/11/2026).
 *
 *   php artisan db:seed --class=FuncionariosSeeder --force
 *
 * Usa a mesma validação da importação de usuários: se alguma linha tiver erro, nada é gravado.
 * Pode rodar de novo: quem já existe é atualizado para ficar igual à lista, ninguém tem a senha
 * trocada, ninguém perde o admin e ninguém fora da lista é desativado. Quem é criado entra com a senha Bin@2026 e
 * troca no primeiro acesso.
 *
 * Para mudar alguém depois do lançamento, use a tela Usuários do site (admin).
 */
class FuncionariosSeeder extends Seeder
{
    private const SENHA_PADRAO = 'Bin@2026';

    /** Quem registra a transferência entre filiais no WinThor. */
    private const FAZ_TRANSFERENCIA = ['isabelly.santos'];

    /**
     * matrícula, nome, login, setor, papel no setor (LIDER/SUBLIDER), marcações, outros setores que aprova.
     * A Meire Vale (9005) não está no WinThor: a matrícula é provisória.
     */
    private const FUNCIONARIOS = [
        [80, 'Camila Bonfim Martins', 'camila.martins', 'ENTRADA', '', [], ''],
        [41, 'Paloma Maclaine do Nascimento', 'paloma.nascimento', 'ENTRADA', 'LIDER', [], ''],
        [145, 'Thaynara Pereira Neves', 'thaynara.neves', 'ENTRADA', '', [], ''],
        [128, 'Bruno Henrique Silva', 'bruno.silva', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [48, 'Geisla Felix Luiz', 'geisla.luiz', 'ESTOQUE', 'LIDER', ['ESTOQUE', 'LIDER_ESTOQUE'], 'SHOWROOM; NINJA PLACE'],
        [14, 'Guilherme Silva de Lima', 'guilherme.lima', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [9003, 'Israel Elias de Oliveira', 'israel.oliveira', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [47, 'Kelber Leone Vilela', 'kelber.vilela', 'ESTOQUE', 'LIDER', ['ESTOQUE', 'LIDER_ESTOQUE', 'APROVA_COMPRAS'], 'SHOWROOM; NINJA PLACE'],
        [153, 'Leticia Christina Diniz Goncalves', 'leticia.goncalves', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [154, 'Lorrane Aparecida Balandino', 'lorrane.balandino', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [25, 'Luci Kelli de Oliveira Goncalves', 'luci.goncalves', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [118, 'Luis Felipe Cunha de Souza', 'luis.souza', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [143, 'Luiz Otavio dos Santos', 'luiz.santos', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [44, 'Miguel Araujo', 'miguel.araujo', 'ESTOQUE', 'LIDER', ['ESTOQUE', 'LIDER_ESTOQUE', 'APROVA_COMPRAS'], 'SHOWROOM; NINJA PLACE'],
        [131, 'Pedro Augusto Gino da Cruz', 'pedro.cruz', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [69, 'Sergio de Castro Jardim', 'sergio.jardim', 'ESTOQUE', 'LIDER', ['ESTOQUE', 'LIDER_ESTOQUE', 'APROVA_COMPRAS'], 'SHOWROOM; NINJA PLACE'],
        [146, 'Vitor Lorran Germano Paulino', 'vitor.paulino', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [9004, 'Washington Luis de Oliveira', 'washington.oliveira', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [13, 'Wesley Diego Conceicao dos Santos', 'wesley.santos', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [116, 'Yuri da Silva Alves', 'yuri.alves', 'ESTOQUE', '', ['ESTOQUE'], ''],
        [35, 'Hayla Vitoria Correa Souza', 'hayla.souza', 'FINANCEIRO/TESOURARIA', 'LIDER', [], ''],
        [152, 'Janaina Regina Pinto', 'janaina.pinto', 'FINANCEIRO/TESOURARIA', '', [], ''],
        [93, 'Thamires Cassimiro Pinto da Silva', 'thamires.silva', 'FINANCEIRO/TESOURARIA', '', [], ''],
        [123, 'Amanda Gabriela de Castro Meireles', 'amanda.meireles', 'NINJA PLACE', '', [], ''],
        [120, 'Fernando Alberto de Morais Junior', 'fernando.junior', 'NINJA PLACE', '', [], ''],
        [31, 'Gleysela Felix', 'gleysela.felix', 'NINJA PLACE', '', [], ''],
        [36, 'Gustavo Almeida Lapa', 'gustavo.lapa', 'NINJA PLACE', 'LIDER', [], ''],
        [45, 'Joao Pedro Silva de Oliveira', 'joao.oliveira', 'NINJA PLACE', '', [], ''],
        [76, 'Rafael Silvino', 'rafael.silvino', 'NINJA PLACE', '', [], ''],
        [130, 'Rafaela Silva', 'rafaela.silva', 'NINJA PLACE', '', [], ''],
        [9005, 'Meire Vale', 'meire.vale', 'RH', '', ['RESP_BAIXA'], ''],
        [56, 'Ana Alice da Rocha Oliveira', 'ana.oliveira', 'SHOWROOM', 'LIDER', [], ''],
        [88, 'Felipe Assis Leite', 'felipe.leite', 'SHOWROOM', '', [], ''],
        [32, 'Isaac Otavio Bernardo', 'isaac.bernardo', 'SHOWROOM', '', [], ''],
        [95, 'Isabelly Rodrigues Santos', 'isabelly.santos', 'SHOWROOM', '', [], ''],
        [78, 'Lara Felix', 'lara.felix', 'SHOWROOM', '', [], ''],
        [100, 'Nicole Martins Assis', 'nicole.assis', 'SHOWROOM', 'LIDER', [], ''],
        [51, 'Osmar Francisco da Luz Junior', 'osmar.junior', 'SHOWROOM', '', [], ''],
        [9, 'Patricia Braga Pederiva', 'patricia.pederiva', 'SHOWROOM', '', [], ''],
        [144, 'Raissa Pereira Martins', 'raissa.martins', 'SHOWROOM', '', [], ''],
        [129, 'Thiago Moseli Ferreira', 'thiago.ferreira', 'SHOWROOM', '', [], ''],
        [19, 'Yhan Otavio Rezende', 'yhan.rezende', 'SHOWROOM', '', [], ''],
        [99, 'Adilson Fonseca', 'adilson.fonseca', 'TI', '', [], ''],
        [40, 'Cesar Augusto da Cruz Marcelino', 'cesar.marcelino', 'TI', '', ['ADMIN'], ''],
        [58, 'Guilherme de Oliveira Santana', 'guilherme.santana', 'TI', 'LIDER', ['ADMIN'], ''],
        [91, 'Kaua Otaviano', 'kaua.otaviano', 'TI', '', [], ''],
        [27, 'Matheus Rodrigues Luiz', 'matheus.luiz', 'TI', '', [], ''],
        [92, 'Otavio Adriano Correia da Silva', 'otavio.silva', 'TI', '', [], ''],
        [57, 'Victor Emanuel Souza Martins', 'victor.martins', 'TI', '', [], ''],
    ];

    private const MARCACOES = ['ESTOQUE', 'LIDER_ESTOQUE', 'RESP_BAIXA', 'ADMIN', 'APROVA_COMPRAS'];

    public function run(ImportacaoUsuarios $importacao): void
    {
        $linhas = [];
        foreach (self::FUNCIONARIOS as $i => [$matricula, $nome, $login, $setor, $papel, $marcacoes, $aprovaSetores]) {
            $colunas = [
                'MATRICULA' => (string) $matricula,
                'NOME' => $nome,
                'LOGIN' => $login,
                'SETOR' => $setor,
                'PAPEL_SETOR' => $papel,
                'APROVA_SETORES' => $aprovaSetores,
                'INCLUIR' => 'SIM',
            ];
            foreach (self::MARCACOES as $marcacao) {
                $colunas[$marcacao] = in_array($marcacao, $marcacoes, true) ? 'SIM' : 'NAO';
            }
            $linhas[] = ['linha' => $i + 1, 'colunas' => $colunas];
        }

        // Quem já é admin continua admin: a lista só dá admin, nunca tira.
        $admins = User::query()->where('is_admin', true)->pluck('id')->all();

        $resultado = $importacao->executar($linhas, senhaPadrao: self::SENHA_PADRAO);

        if ($resultado->falhou()) {
            throw new RuntimeException("Funcionários não carregados:\n".implode("\n", $resultado->erros));
        }

        User::query()->whereIn('id', $admins)->update(['is_admin' => true]);
        User::query()->whereIn('login', self::FAZ_TRANSFERENCIA)->update(['faz_transferencia' => true]);

        foreach ($resultado->avisos as $aviso) {
            $this->command?->warn($aviso);
        }
        $this->command?->info(count($resultado->criados).' criado(s), '.count($resultado->atualizados).' atualizado(s), '.count(self::FUNCIONARIOS).' na lista.');
    }
}
