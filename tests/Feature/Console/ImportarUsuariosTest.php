<?php

namespace Tests\Feature\Console;

use App\Enums\PapelSetor;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportarUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private const CABECALHO = ['MATRICULA', 'NOME', 'LOGIN', 'LOGIN_WINTHOR', 'CARGO', 'SETOR', 'PAPEL_SETOR', 'ESTOQUE', 'LIDER_ESTOQUE', 'RESP_BAIXA', 'ADMIN', 'INCLUIR', 'SUA_ANOTACAO'];

    /** @var list<string> */
    private array $arquivos = [];

    protected function tearDown(): void
    {
        foreach ($this->arquivos as $arquivo) {
            @unlink($arquivo);
        }

        parent::tearDown();
    }

    /**
     * Linhas no formato da planilha real (aba USUARIOS).
     *
     * @return list<list<int|string>>
     */
    private function linhasPadrao(): array
    {
        return [
            [48, 'Geisla Felix Luiz', 'geisla.luiz', 'GEISLA.FELIX', 'Auxiliar de Estoque', 'ESTOQUE', 'LÍDER', 'SIM', 'SIM', 'NÃO', 'NÃO', 'SIM', 'líder do estoque'],
            [128, 'Bruno Henrique Silva', 'bruno.silva', 'BRUNO.SILVA', 'RMA', 'ESTOQUE', '', 'SIM', 'NÃO', 'NÃO', 'NÃO', 'SIM', ''],
            [27, 'Matheus Rodrigues Luiz', 'matheus.luiz', 'MATHEUS.LUIZ', 'Analista de Sistemas', 'TI', 'LÍDER', 'NÃO', 'NÃO', 'NÃO', 'NÃO', 'SIM', ''],
            [40, 'Cesar Augusto da Cruz Marcelino', 'cesar.marcelino', 'SUPORTE3', 'Auxiliar de TI', 'TI', 'SUBLÍDER', 'NÃO', 'NÃO', 'NÃO', 'NÃO', 'SIM', ''],
            [58, 'Guilherme de Oliveira Santana', 'guilherme.santana', 'SUPORTE4', 'Auxiliar de TI', 'TI', '', 'NÃO', 'NÃO', 'NÃO', 'SIM', 'SIM', 'admin'],
            [90, 'Erica Ferreira da Cruz', 'erica.cruz', 'ERICA.RH', 'Assistente de Recursos Humanos', 'RH', '', 'NÃO', 'NÃO', 'SIM', 'NÃO', 'SIM', 'baixa'],
            [118, 'Luis Felipe Cunha de Souza', 'luis.souza', 'LUISS', '', 'SHOWROOM', '', 'NÃO', 'NÃO', 'NÃO', 'NÃO', 'SIM', ''],
        ];
    }

    /**
     * Planilha .xlsx com as mesmas abas da real; os usuários ficam na aba USUARIOS.
     *
     * @param  list<list<int|string>>  $linhas
     */
    private function planilha(array $linhas): string
    {
        $caminho = sys_get_temp_dir().DIRECTORY_SEPARATOR.'carga-'.uniqid().'.xlsx';
        $this->arquivos[] = $caminho;

        $escritor = new Writer;
        $escritor->openToFile($caminho);
        $escritor->getCurrentSheet()->setName('PENDENCIAS');
        $escritor->addRow(Row::fromValues(['#', 'O QUE FALTA DECIDIR', 'SUA RESPOSTA']));
        $escritor->addNewSheetAndMakeItCurrent()->setName('USUARIOS');
        $escritor->addRow(Row::fromValues(self::CABECALHO));
        foreach ($linhas as $linha) {
            $escritor->addRow(Row::fromValues($linha));
        }
        $escritor->addNewSheetAndMakeItCurrent()->setName('SETORES');
        $escritor->addRow(Row::fromValues(['SETOR', 'PESSOAS']));
        $escritor->close();

        return $caminho;
    }

    public function test_cria_pessoas_setores_papeis_e_aprovadores(): void
    {
        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($this->linhasPadrao())])
            ->expectsOutputToContain('criadas: 7')
            ->assertSuccessful();

        $this->assertSame(['ESTOQUE', 'RH', 'SHOWROOM', 'TI'], Setor::orderBy('nome')->pluck('nome')->all());

        $geisla = User::where('login', 'geisla.luiz')->firstOrFail();
        $this->assertSame(48, $geisla->matricula);
        $this->assertSame('Geisla Felix Luiz', $geisla->nome);
        $this->assertSame('Auxiliar de Estoque', $geisla->cargo);
        $this->assertSame('ESTOQUE', $geisla->setor()->value('nome'));
        $this->assertTrue($geisla->is_estoque && $geisla->is_lider_estoque);
        $this->assertTrue($geisla->ativo && $geisla->deve_trocar_senha);

        $this->assertTrue(User::where('login', 'guilherme.santana')->value('is_admin'));
        $this->assertTrue(User::where('login', 'erica.cruz')->value('is_responsavel_baixa'));
        $this->assertFalse(User::where('login', 'bruno.silva')->value('is_lider_estoque'));
        $this->assertNull(User::where('login', 'luis.souza')->value('cargo'));

        $ti = Setor::where('nome', 'TI')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['matheus.luiz' => PapelSetor::LIDER, 'cesar.marcelino' => PapelSetor::SUBLIDER],
            $ti->aprovadores()->get()->mapWithKeys(fn (User $u) => [$u->login => $u->pivot->papel])->all(),
        );
        // Showroom e RH sem aprovador: a aprovação cai para os líderes do estoque (regra do sistema).
        $this->assertSame(0, Setor::where('nome', 'SHOWROOM')->firstOrFail()->aprovadores()->count());
    }

    public function test_mostra_a_senha_provisoria_de_cada_pessoa_nova(): void
    {
        Artisan::call('usuarios:importar', ['arquivo' => $this->planilha(array_slice($this->linhasPadrao(), 0, 2))]);
        $saida = Artisan::output();

        foreach (['geisla.luiz', 'bruno.silva'] as $login) {
            $this->assertMatchesRegularExpression("/\\b{$login}\\s*\\|\\s*(\\S+)/", $saida);
            preg_match("/\\b{$login}\\s*\\|\\s*(\\S+)/", $saida, $achado);
            $this->assertTrue(Hash::check($achado[1], User::where('login', $login)->value('password')), $login);
        }
    }

    public function test_simulacao_nao_grava_nada(): void
    {
        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($this->linhasPadrao()), '--dry-run' => true])
            ->expectsOutputToContain('Simulação')
            ->expectsOutputToContain('criadas: 7')
            ->assertSuccessful();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Setor::count());
    }

    public function test_importar_de_novo_nao_duplica_nem_troca_a_senha(): void
    {
        $arquivo = $this->planilha($this->linhasPadrao());
        $this->artisan('usuarios:importar', ['arquivo' => $arquivo])->assertSuccessful();
        $senhas = User::orderBy('id')->pluck('password', 'login')->all();

        $this->artisan('usuarios:importar', ['arquivo' => $arquivo])
            ->expectsOutputToContain('criadas: 0, atualizadas: 0, sem mudança: 7')
            ->assertSuccessful();

        $this->assertSame(7, User::count());
        $this->assertSame($senhas, User::orderBy('id')->pluck('password', 'login')->all());
    }

    public function test_atualiza_quando_a_planilha_muda(): void
    {
        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($this->linhasPadrao())])->assertSuccessful();

        $linhas = $this->linhasPadrao();
        // Cesar vira líder do Showroom; Matheus passa a ser o único aprovador da TI.
        $linhas[3][4] = 'Supervisor de Vendas';
        $linhas[3][5] = 'SHOWROOM';
        $linhas[3][6] = 'LÍDER';

        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($linhas)])
            ->expectsOutputToContain('atualizadas: 1')
            ->assertSuccessful();

        $cesar = User::where('login', 'cesar.marcelino')->firstOrFail();
        $this->assertSame('Supervisor de Vendas', $cesar->cargo);
        $this->assertSame('SHOWROOM', $cesar->setor()->value('nome'));
        $this->assertSame(['matheus.luiz'], Setor::where('nome', 'TI')->firstOrFail()->aprovadores()->pluck('login')->all());
        $this->assertSame(['cesar.marcelino'], Setor::where('nome', 'SHOWROOM')->firstOrFail()->aprovadores()->pluck('login')->all());
    }

    public function test_linha_com_erro_barra_a_importacao_inteira(): void
    {
        $linhas = $this->linhasPadrao();
        $linhas[1][2] = 'geisla.luiz';   // login repetido
        $linhas[2][5] = '';              // sem setor
        $linhas[3][6] = 'GERENTE';       // papel que não existe
        $linhas[5][7] = 'SIM';           // estoque fora do setor Estoque

        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($linhas)])
            ->expectsOutputToContain('Linha 3: login geisla.luiz repetido')
            ->expectsOutputToContain('Linha 4: setor em branco')
            ->expectsOutputToContain('Linha 5: PAPEL_SETOR "GERENTE"')
            ->expectsOutputToContain('Linha 7: ESTOQUE = SIM só vale para quem é do setor ESTOQUE')
            ->expectsOutputToContain('Nada foi gravado')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_incluir_nao_desativa_e_revisar_fica_de_fora(): void
    {
        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($this->linhasPadrao())])->assertSuccessful();

        $linhas = $this->linhasPadrao();
        $linhas[1][11] = 'NÃO';
        $linhas[6][11] = 'REVISAR';

        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($linhas)])
            ->expectsOutputToContain('desativadas: 1')
            ->expectsOutputToContain('Linha 8: INCLUIR = "REVISAR"')
            ->assertSuccessful();

        $this->assertFalse(User::where('login', 'bruno.silva')->value('ativo'));
        $this->assertTrue(User::where('login', 'luis.souza')->value('ativo'));
    }

    public function test_quem_saiu_da_planilha_so_e_desativado_quando_pedido(): void
    {
        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($this->linhasPadrao())])->assertSuccessful();
        $semErica = array_values(array_filter($this->linhasPadrao(), fn (array $linha) => $linha[2] !== 'erica.cruz'));
        $arquivo = $this->planilha($semErica);

        $this->artisan('usuarios:importar', ['arquivo' => $arquivo])
            ->expectsOutputToContain('Fora da planilha')
            ->assertSuccessful();
        $this->assertTrue(User::where('login', 'erica.cruz')->value('ativo'));

        $this->artisan('usuarios:importar', ['arquivo' => $arquivo, '--desativar-ausentes' => true])->assertSuccessful();
        $this->assertFalse(User::where('login', 'erica.cruz')->value('ativo'));
    }

    public function test_senha_de_teste_vale_para_todos_e_dispensa_a_troca(): void
    {
        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($this->linhasPadrao()), '--senha-de-teste' => 'teste123'])
            ->assertSuccessful();

        foreach (User::all() as $user) {
            $this->assertTrue(Hash::check('teste123', $user->password), $user->login);
            $this->assertFalse($user->deve_trocar_senha, $user->login);
        }
    }

    public function test_senha_de_teste_e_recusada_em_producao(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('usuarios:importar', ['arquivo' => $this->planilha($this->linhasPadrao()), '--senha-de-teste' => 'teste123'])
            ->expectsOutputToContain('não pode ser usada em produção')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_le_csv_do_excel_com_ponto_e_virgula_e_acentos_ansi(): void
    {
        $caminho = sys_get_temp_dir().DIRECTORY_SEPARATOR.'carga-'.uniqid().'.csv';
        $this->arquivos[] = $caminho;
        $conteudo = implode(';', self::CABECALHO)."\r\n"
            ."27;Matheus Rodrigues Luiz;matheus.luiz;MATHEUS.LUIZ;Analista de Sistemas;TI;LÍDER;NÃO;NÃO;NÃO;NÃO;SIM;\r\n";
        file_put_contents($caminho, mb_convert_encoding($conteudo, 'Windows-1252', 'UTF-8'));

        $this->artisan('usuarios:importar', ['arquivo' => $caminho])->assertSuccessful();

        $this->assertSame(['matheus.luiz'], Setor::where('nome', 'TI')->firstOrFail()->aprovadores()->pluck('login')->all());
    }

    public function test_arquivo_que_nao_existe(): void
    {
        $this->artisan('usuarios:importar', ['arquivo' => 'nao-existe.xlsx'])
            ->expectsOutputToContain('Arquivo não encontrado')
            ->assertFailed();
    }
}
