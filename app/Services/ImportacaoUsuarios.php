<?php

namespace App\Services;

use App\Enums\PapelSetor;
use App\Models\Setor;
use App\Models\User;
use App\Support\PlanilhaUsuarios;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cria e atualiza pessoas, setores e aprovadores a partir da planilha de carga.
 *
 * A planilha manda: quem está com INCLUIR = SIM fica ativo com os dados e papéis dela;
 * INCLUIR = NÃO desativa. Qualquer erro em qualquer linha barra tudo (nada é gravado).
 * Rodar de novo com a mesma planilha não muda nada e nunca troca a senha de ninguém.
 *
 * Com $soIncluir a planilha só acrescenta: cria quem ainda não está no sistema e soma os
 * aprovadores marcados nela (PAPEL_SETOR e APROVA_SETORES). Quem já existe fica com os
 * dados, o setor e a senha que tem, e nenhum aprovador sai.
 */
class ImportacaoUsuarios
{
    private const OBRIGATORIAS = ['MATRICULA', 'NOME', 'LOGIN', 'SETOR'];

    private const MARCACOES = ['ESTOQUE', 'LIDER_ESTOQUE', 'RESP_BAIXA', 'ADMIN'];

    /**
     * @param  list<array{linha: int, colunas: array<string, string>}>  $linhas  saída de PlanilhaUsuarios::ler()
     * @param  bool  $simular  faz tudo numa transação desfeita no fim: o relatório sai igual, o banco não muda
     * @param  bool  $desativarAusentes  desativa quem está ativo no sistema e não aparece na planilha
     * @param  ?string  $senhaDeTeste  fora de produção: todos com esta senha, sem troca obrigatória
     * @param  ?string  $senhaPadrao  pessoas novas entram com esta senha e trocam no primeiro acesso
     * @param  bool  $soIncluir  só cria quem falta e soma aprovadores; não mexe em quem já existe
     */
    public function executar(array $linhas, bool $simular = false, bool $desativarAusentes = false, ?string $senhaDeTeste = null, ?string $senhaPadrao = null, bool $soIncluir = false): ResultadoImportacao
    {
        $resultado = new ResultadoImportacao;
        [$incluir, $desativar] = $this->interpretar($linhas, $resultado, $soIncluir);

        if ($soIncluir && $desativar !== []) {
            $resultado->avisos[] = count($desativar).' linha(s) com INCLUIR = NÃO ignorada(s): só incluindo, ninguém é desativado.';
        }

        $existentes = $soIncluir ? User::query()->whereIn('matricula', array_column($incluir, 'matricula'))->pluck('id', 'matricula')->all() : [];
        $this->conferirLogins(array_values(array_filter($incluir, fn (array $p) => ! isset($existentes[$p['matricula']]))), $resultado);

        if ($resultado->falhou()) {
            return $resultado;
        }

        DB::beginTransaction();

        try {
            $soIncluir
                ? $this->incluirNovos($incluir, $senhaDeTeste, $senhaPadrao, $resultado)
                : $this->aplicar($incluir, $desativar, $desativarAusentes, $senhaDeTeste, $senhaPadrao, $resultado);
            $simular ? DB::rollBack() : DB::commit();
        } catch (Throwable $erro) {
            DB::rollBack();

            throw $erro;
        }

        return $resultado;
    }

    /**
     * Valida linha a linha e separa quem entra de quem sai.
     *
     * @param  list<array{linha: int, colunas: array<string, string>}>  $linhas
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function interpretar(array $linhas, ResultadoImportacao $resultado, bool $soIncluir): array
    {
        if ($linhas === []) {
            $resultado->erros[] = 'A aba USUARIOS não tem nenhuma pessoa.';

            return [[], []];
        }

        foreach (self::OBRIGATORIAS as $obrigatoria) {
            if (! array_key_exists($obrigatoria, $linhas[0]['colunas'])) {
                $resultado->erros[] = "A planilha não tem a coluna {$obrigatoria}.";
            }
        }

        if ($resultado->falhou()) {
            return [[], []];
        }

        $incluir = [];
        $desativar = [];
        $matriculas = [];
        $logins = [];

        foreach ($linhas as ['linha' => $numero, 'colunas' => $c]) {
            $erro = fn (string $mensagem) => $resultado->erros[] = "Linha {$numero}: {$mensagem}";
            $situacao = array_key_exists('INCLUIR', $c) ? PlanilhaUsuarios::normalizar($c['INCLUIR']) : 'SIM';

            if (! in_array($situacao, ['SIM', 'NAO'], true)) {
                $resultado->avisos[] = "Linha {$numero}: INCLUIR = \"{$c['INCLUIR']}\", {$c['NOME']} ficou de fora (use SIM ou NÃO).";

                continue;
            }

            $matricula = ctype_digit($c['MATRICULA']) ? (int) $c['MATRICULA'] : 0;
            if ($matricula < 1 || $matricula > 4294967295) {
                $erro("matrícula inválida (\"{$c['MATRICULA']}\")");

                continue;
            }
            if (isset($matriculas[$matricula])) {
                $erro("matrícula {$matricula} repetida (também na linha {$matriculas[$matricula]})");
            }
            $matriculas[$matricula] = $numero;

            if ($situacao === 'NAO') {
                $desativar[] = ['linha' => $numero, 'matricula' => $matricula];

                continue;
            }

            $pessoa = [
                'linha' => $numero,
                'matricula' => $matricula,
                'nome' => $c['NOME'],
                'login' => mb_strtolower($c['LOGIN']),
                'cargo' => ($c['CARGO'] ?? '') !== '' ? $c['CARGO'] : null,
                'setor' => mb_strtoupper($c['SETOR']),
                'papel' => $this->papel($c['PAPEL_SETOR'] ?? '', $erro),
                // Outros setores que a pessoa também aprova, como líder (ex.: líder do estoque aprovando o Showroom).
                'aprova_setores' => array_values(array_unique(array_filter(array_map(
                    fn (string $nome) => mb_strtoupper(trim($nome)),
                    preg_split('/[;,]/', $c['APROVA_SETORES'] ?? '') ?: [],
                ), fn (string $nome) => $nome !== ''))),
            ];
            foreach (self::MARCACOES as $marcacao) {
                $pessoa[$marcacao] = $this->simOuNao($marcacao, $c[$marcacao] ?? '', $erro);
            }

            if ($pessoa['nome'] === '' || mb_strlen($pessoa['nome']) > 255) {
                $erro($pessoa['nome'] === '' ? 'nome em branco' : 'nome com mais de 255 letras');
            }
            if ($pessoa['login'] === '') {
                $erro('login em branco');
            } elseif (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $pessoa['login']) || strlen($pessoa['login']) > 60) {
                $erro("login \"{$pessoa['login']}\" inválido (use letras minúsculas, números e ponto, como geisla.luiz)");
            } elseif (isset($logins[$pessoa['login']])) {
                $erro("login {$pessoa['login']} repetido (também na linha {$logins[$pessoa['login']]})");
            } else {
                $logins[$pessoa['login']] = $numero;
            }
            if ($pessoa['setor'] === '') {
                $erro('setor em branco');
            } elseif (mb_strlen($pessoa['setor']) > 80) {
                $erro('nome do setor com mais de 80 letras');
            }
            if ($pessoa['cargo'] !== null && mb_strlen($pessoa['cargo']) > 100) {
                $erro('cargo com mais de 100 letras');
            }
            // Regra do sistema: papel de estoque é só de quem é do setor Estoque.
            if ($pessoa['ESTOQUE'] && $pessoa['setor'] !== 'ESTOQUE') {
                $erro('ESTOQUE = SIM só vale para quem é do setor ESTOQUE');
            }
            if ($pessoa['LIDER_ESTOQUE'] && ! $pessoa['ESTOQUE']) {
                $erro('LIDER_ESTOQUE = SIM exige ESTOQUE = SIM');
            }

            $incluir[] = $pessoa;
        }

        // APROVA_SETORES só vale para setor que aparece na planilha (ou, só incluindo, que já existe
        // no sistema), e não para o próprio (esse é o PAPEL_SETOR).
        $setoresDaPlanilha = array_flip(array_column($incluir, 'setor'));
        if ($soIncluir) {
            $setoresDaPlanilha += array_flip(Setor::query()->pluck('nome')->map(fn (string $nome) => mb_strtoupper($nome))->all());
        }
        foreach ($incluir as $pessoa) {
            foreach ($pessoa['aprova_setores'] as $outro) {
                if ($outro === $pessoa['setor']) {
                    $resultado->erros[] = "Linha {$pessoa['linha']}: APROVA_SETORES tem o próprio setor ({$outro}); para isso use PAPEL_SETOR";
                } elseif (! isset($setoresDaPlanilha[$outro])) {
                    $resultado->erros[] = "Linha {$pessoa['linha']}: APROVA_SETORES tem o setor {$outro}, que não é de ninguém na planilha"
                        .($soIncluir ? ' nem existe no sistema' : '');
                }
            }
        }

        return [$incluir, $desativar];
    }

    private function papel(string $valor, Closure $erro): ?PapelSetor
    {
        $papel = match (PlanilhaUsuarios::normalizar($valor)) {
            '' => null,
            'LIDER' => PapelSetor::LIDER,
            'SUBLIDER' => PapelSetor::SUBLIDER,
            default => false,
        };

        if ($papel === false) {
            $erro("PAPEL_SETOR \"{$valor}\" não existe (use LÍDER, SUBLÍDER ou deixe em branco)");

            return null;
        }

        return $papel;
    }

    private function simOuNao(string $coluna, string $valor, Closure $erro): bool
    {
        $marcado = match (PlanilhaUsuarios::normalizar($valor)) {
            'SIM', 'S' => true,
            '', 'NAO', 'N' => false,
            default => null,
        };

        if ($marcado === null) {
            $erro("{$coluna} \"{$valor}\" inválido (use SIM ou NÃO)");

            return false;
        }

        return $marcado;
    }

    /**
     * Login da planilha que já pertence a outra pessoa no sistema (outra matrícula).
     *
     * @param  list<array<string, mixed>>  $incluir
     */
    private function conferirLogins(array $incluir, ResultadoImportacao $resultado): void
    {
        if ($resultado->falhou()) {
            return;
        }

        $donos = User::query()->whereIn('login', array_column($incluir, 'login'))->get(['id', 'login', 'nome', 'matricula'])->keyBy('login');

        foreach ($incluir as $pessoa) {
            $dono = $donos->get($pessoa['login']);
            if ($dono !== null && (int) $dono->matricula !== $pessoa['matricula']) {
                $resultado->erros[] = "Linha {$pessoa['linha']}: o login {$pessoa['login']} já é de {$dono->nome}"
                    .($dono->matricula ? " (matrícula {$dono->matricula})" : '').' no sistema';
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $incluir
     * @param  list<array<string, mixed>>  $desativar
     */
    private function aplicar(array $incluir, array $desativar, bool $desativarAusentes, ?string $senhaDeTeste, ?string $senhaPadrao, ResultadoImportacao $resultado): void
    {
        $setores = [];
        foreach (array_unique(array_column($incluir, 'setor')) as $nome) {
            $setor = Setor::query()->firstOrCreate(['nome' => $nome]);
            if ($setor->wasRecentlyCreated) {
                $resultado->setoresCriados[] = $nome;
            }
            $setores[$nome] = $setor;
        }

        $aprovadores = array_fill_keys(array_keys($setores), []);

        foreach ($incluir as $pessoa) {
            $user = User::query()->firstWhere('matricula', $pessoa['matricula']) ?? new User;
            $novo = ! $user->exists;
            $estavaInativo = $user->exists && ! $user->ativo;

            $user->forceFill([
                'matricula' => $pessoa['matricula'],
                'nome' => $pessoa['nome'],
                'login' => $pessoa['login'],
                'cargo' => $pessoa['cargo'],
                'setor_id' => $setores[$pessoa['setor']]->id,
                'is_estoque' => $pessoa['ESTOQUE'],
                'is_lider_estoque' => $pessoa['LIDER_ESTOQUE'],
                'is_responsavel_baixa' => $pessoa['RESP_BAIXA'],
                'is_admin' => $pessoa['ADMIN'],
                'ativo' => true,
            ]);
            $mudou = array_keys($user->getDirty());

            if ($novo) {
                $senha = $senhaDeTeste ?? $senhaPadrao ?? $this->senhaProvisoria();
                $user->forceFill(['password' => $senha, 'deve_trocar_senha' => $senhaDeTeste === null]);
                $resultado->criados[$pessoa['login']] = ['nome' => $pessoa['nome'], 'senha' => $senhaDeTeste === null && $senhaPadrao === null ? $senha : null];
            } elseif ($senhaDeTeste !== null) {
                $user->forceFill(['password' => $senhaDeTeste, 'deve_trocar_senha' => false]);
            }

            if (! $novo) {
                if ($mudou !== []) {
                    $resultado->atualizados[$pessoa['login']] = $mudou;
                } else {
                    $resultado->semMudanca++;
                }
                if ($estavaInativo) {
                    $resultado->reativados[] = "{$pessoa['nome']} ({$pessoa['login']})";
                }
            }

            $user->save();

            if ($pessoa['papel'] !== null) {
                $aprovadores[$pessoa['setor']][$user->id] = ['papel' => $pessoa['papel']];
                $resultado->aprovadores[$pessoa['setor']][] = "{$pessoa['nome']} (".mb_strtolower($pessoa['papel']->rotulo()).')';
            }

            foreach ($pessoa['aprova_setores'] as $outro) {
                $aprovadores[$outro][$user->id] = ['papel' => PapelSetor::LIDER];
                $resultado->aprovadores[$outro][] = "{$pessoa['nome']} (líder, do setor ".mb_strtolower($pessoa['setor']).')';
            }
        }

        // Os aprovadores de cada setor da planilha passam a ser exatamente os marcados nela.
        foreach ($setores as $nome => $setor) {
            $setor->aprovadores()->sync($aprovadores[$nome]);
            $resultado->aprovadores[$nome] ??= [];
        }
        ksort($resultado->aprovadores);

        foreach ($desativar as $saida) {
            $this->desativar(User::query()->firstWhere('matricula', $saida['matricula']), $resultado);
        }

        $naPlanilha = array_merge(array_column($incluir, 'matricula'), array_column($desativar, 'matricula'));
        $ausentes = User::query()
            ->where('ativo', true)
            ->where(fn ($q) => $q->whereNull('matricula')->orWhereNotIn('matricula', $naPlanilha))
            ->orderBy('nome')
            ->get();

        foreach ($ausentes as $ausente) {
            $desativarAusentes
                ? $this->desativar($ausente, $resultado)
                : $resultado->foraDaPlanilha[] = "{$ausente->nome} ({$ausente->login})";
        }

        $resultado->temLiderDoEstoqueAtivo = User::query()->where('ativo', true)->where('is_lider_estoque', true)->exists();
    }

    /**
     * Só incluindo: cria quem não existe e soma os aprovadores da planilha, sem tirar nenhum.
     *
     * @param  list<array<string, mixed>>  $incluir
     */
    private function incluirNovos(array $incluir, ?string $senhaDeTeste, ?string $senhaPadrao, ResultadoImportacao $resultado): void
    {
        $setores = [];
        $setor = function (string $nome) use (&$setores, $resultado): Setor {
            if (! isset($setores[$nome])) {
                $setores[$nome] = Setor::query()->firstOrCreate(['nome' => $nome]);
                if ($setores[$nome]->wasRecentlyCreated) {
                    $resultado->setoresCriados[] = $nome;
                }
            }

            return $setores[$nome];
        };
        $existentes = User::query()->whereIn('matricula', array_column($incluir, 'matricula'))->get()->keyBy('matricula');
        $somar = function (User $user, string $nome, PapelSetor $papel, string $rotulo) use ($setor, $resultado): void {
            $alvo = $setor($nome);
            if (! $alvo->aprovadores()->whereKey($user->id)->exists()) {
                $alvo->aprovadores()->attach($user->id, ['papel' => $papel]);
                $resultado->aprovadores[$nome][] = "{$user->nome} ({$rotulo})";
            }
        };

        foreach ($incluir as $pessoa) {
            $user = $existentes->get($pessoa['matricula']);

            if ($user === null) {
                $senha = $senhaDeTeste ?? $senhaPadrao ?? $this->senhaProvisoria();
                $user = (new User)->forceFill([
                    'matricula' => $pessoa['matricula'],
                    'nome' => $pessoa['nome'],
                    'login' => $pessoa['login'],
                    'cargo' => $pessoa['cargo'],
                    'setor_id' => $setor($pessoa['setor'])->id,
                    'is_estoque' => $pessoa['ESTOQUE'],
                    'is_lider_estoque' => $pessoa['LIDER_ESTOQUE'],
                    'is_responsavel_baixa' => $pessoa['RESP_BAIXA'],
                    'is_admin' => $pessoa['ADMIN'],
                    'ativo' => true,
                    'password' => $senha,
                    'deve_trocar_senha' => $senhaDeTeste === null,
                ]);
                $user->save();
                $resultado->criados[$pessoa['login']] = ['nome' => $pessoa['nome'], 'senha' => $senhaDeTeste === null && $senhaPadrao === null ? $senha : null];
            } else {
                $resultado->semMudanca++;
            }

            // O papel vale também para quem já existe: soma o aprovador, sem mudar o setor da pessoa.
            if ($pessoa['papel'] !== null) {
                $somar($user, $pessoa['setor'], $pessoa['papel'], mb_strtolower($pessoa['papel']->rotulo()));
            }

            foreach ($pessoa['aprova_setores'] as $outro) {
                $somar($user, $outro, PapelSetor::LIDER, 'líder, do setor '.mb_strtolower($user->setor()->value('nome') ?? $pessoa['setor']));
            }
        }
        ksort($resultado->aprovadores);

        $resultado->temLiderDoEstoqueAtivo = User::query()->where('ativo', true)->where('is_lider_estoque', true)->exists();
    }

    private function desativar(?User $user, ResultadoImportacao $resultado): void
    {
        if ($user === null || ! $user->ativo) {
            return;
        }

        $user->forceFill(['ativo' => false])->save();
        $resultado->desativados[] = "{$user->nome} ({$user->login})";
    }

    /**
     * 8 letras e números, sem os que se confundem no papel (0/o, 1/l/i).
     */
    private function senhaProvisoria(): string
    {
        $alfabeto = 'abcdefghjkmnpqrstuvwxyz23456789';

        do {
            $senha = '';
            for ($i = 0; $i < 8; $i++) {
                $senha .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
        } while (! preg_match('/\d/', $senha) || ! preg_match('/[a-z]/', $senha));

        return $senha;
    }
}
