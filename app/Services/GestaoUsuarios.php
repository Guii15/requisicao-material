<?php

namespace App\Services;

use App\Enums\PapelSetor;
use App\Models\Setor;
use App\Models\User;
use App\Models\UsuarioAlteracao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Tela Usuários: o administrador cria logins e dá ou tira permissões sem mexer no banco.
 * Tudo o que muda fica no histórico (usuario_alteracoes), com quem fez e quando.
 *
 * Mesmas regras da importação: papel de estoque só para quem é do setor ESTOQUE e líder do
 * estoque precisa ser do estoque. Ninguém tira o próprio acesso de administrador nem se
 * desativa, e o sistema nunca fica sem um administrador ativo.
 */
class GestaoUsuarios
{
    /** Senha inicial de todos os logins (Bin@2026). */
    public const SENHA_INICIAL = 'Bin@2026';

    /** Permissões marcadas por caixinha na tela, com o rótulo usado no histórico. */
    public const PERMISSOES = [
        'is_estoque' => 'Estoque (separa e confere devolução)',
        'is_lider_estoque' => 'Líder do estoque (libera e aprova Showroom, Ninja e setores sem líder)',
        'is_responsavel_baixa' => 'Responsável pela baixa e pela decisão das compras',
        'aprova_compras' => 'Aprova pedido de compra de funcionário',
        'faz_transferencia' => 'Faz transferência entre filiais (WinThor)',
        'is_admin' => 'Administrador (cria logins e dá permissões)',
    ];

    /**
     * Cria (quando $alvo é nulo) ou altera um usuário.
     *
     * @param  array<string, mixed>  $dados
     */
    public function salvar(?User $alvo, array $dados, User $por): User
    {
        Gate::forUser($por)->authorize('gerenciar-usuarios');
        $novo = $alvo === null;
        $validado = $this->validar($alvo, $dados);
        $this->protegerAdministradores($alvo, $validado, $por);

        return DB::transaction(function () use ($alvo, $novo, $validado, $por) {
            $user = $alvo ?? new User;
            $antes = $novo ? [] : $this->retrato($user);

            $user->forceFill([
                'matricula' => $validado['matricula'],
                'nome' => $validado['nome'],
                'login' => $validado['login'],
                'cargo' => $validado['cargo'],
                'setor_id' => $validado['setor_id'],
                'ativo' => $validado['ativo'],
                ...array_map(fn ($valor) => (bool) $valor, array_intersect_key($validado, self::PERMISSOES)),
            ]);

            if ($novo) {
                $user->forceFill(['password' => $validado['senha'], 'deve_trocar_senha' => true]);
            }

            $user->save();
            $this->sincronizarAprovacoes($user, $validado['papel_setor'], $validado['aprova_setores']);

            $depois = $this->retrato($user->fresh());
            $mudancas = $novo ? $depois : $this->diferenca($antes, $depois);

            if ($novo || $mudancas !== []) {
                $this->registrar($user, $por, $novo ? 'CRIACAO' : 'ALTERACAO', $mudancas);
            }

            return $user;
        });
    }

    /**
     * Senha provisória definida pelo administrador. A pessoa troca no próximo acesso.
     */
    public function resetarSenha(User $alvo, ?string $senha, User $por): void
    {
        Gate::forUser($por)->authorize('gerenciar-usuarios');

        $validado = Validator::make(['senha' => $senha], [
            'senha' => ['required', 'string', Password::min(8)->letters()->numbers()],
        ], [], ['senha' => 'senha provisória'])->validate();

        DB::transaction(function () use ($alvo, $validado, $por) {
            $alvo->forceFill(['password' => $validado['senha'], 'deve_trocar_senha' => true])->save();
            $this->registrar($alvo, $por, 'SENHA_RESETADA', null);
        });
    }

    /**
     * Quem ainda entra com a senha inicial passa a ter que criar a própria senha no próximo
     * acesso. Quem já trocou não é afetado. Devolve quantas pessoas foram marcadas.
     */
    public function exigirTrocaDaSenhaInicial(User $por): int
    {
        Gate::forUser($por)->authorize('gerenciar-usuarios');

        $marcados = 0;
        User::query()->where('ativo', true)->where('deve_trocar_senha', false)->each(function (User $alvo) use ($por, &$marcados) {
            if (! Hash::check(self::SENHA_INICIAL, $alvo->password)) {
                return;
            }
            DB::transaction(function () use ($alvo, $por) {
                $alvo->forceFill(['deve_trocar_senha' => true])->save();
                $this->registrar($alvo, $por, 'SENHA_RESETADA', null);
            });
            $marcados++;
        });

        return $marcados;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function validar(?User $alvo, array $dados): array
    {
        $texto = fn (mixed $v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v;
        $dados['nome'] = $texto($dados['nome'] ?? null);
        $dados['login'] = is_string($dados['login'] ?? null) ? mb_strtolower(trim($dados['login'])) : ($dados['login'] ?? null);
        $dados['cargo'] = $texto($dados['cargo'] ?? null);
        $dados['matricula'] = $texto($dados['matricula'] ?? null);
        $dados['papel_setor'] = $texto($dados['papel_setor'] ?? null);
        $dados['aprova_setores'] = array_values(array_filter((array) ($dados['aprova_setores'] ?? []), fn ($id) => is_numeric($id)));
        $dados['ativo'] = filter_var($dados['ativo'] ?? false, FILTER_VALIDATE_BOOL);
        foreach (array_keys(self::PERMISSOES) as $permissao) {
            $dados[$permissao] = filter_var($dados[$permissao] ?? false, FILTER_VALIDATE_BOOL);
        }

        $validado = Validator::make($dados, [
            'nome' => ['required', 'string', 'max:255'],
            'login' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', Rule::unique('users', 'login')->ignore($alvo?->id)],
            'matricula' => ['nullable', 'integer', 'min:1', 'max:4294967295', Rule::unique('users', 'matricula')->ignore($alvo?->id)],
            'cargo' => ['nullable', 'string', 'max:100'],
            'setor_id' => ['required', 'integer', Rule::exists('setores', 'id')->where('ativo', true)],
            'papel_setor' => ['nullable', Rule::enum(PapelSetor::class)],
            'aprova_setores' => ['array'],
            'aprova_setores.*' => ['integer', 'distinct', Rule::exists('setores', 'id')->where('ativo', true)],
            'senha' => $alvo === null ? ['required', 'string', Password::min(8)->letters()->numbers()] : ['exclude'],
            'ativo' => ['boolean'],
            ...array_fill_keys(array_keys(self::PERMISSOES), ['boolean']),
        ], [
            'login.regex' => 'Use letras minúsculas, números e ponto, como geisla.luiz.',
            'login.unique' => 'Esse login já é de outra pessoa.',
            'matricula.unique' => 'Essa matrícula já é de outra pessoa.',
        ], [
            'nome' => 'nome',
            'login' => 'login',
            'matricula' => 'matrícula',
            'cargo' => 'cargo',
            'setor_id' => 'setor',
            'papel_setor' => 'papel no setor',
            'aprova_setores' => 'outros setores que aprova',
            'senha' => 'senha inicial',
        ])->validate();

        $validado['matricula'] = $validado['matricula'] !== null ? (int) $validado['matricula'] : null;
        $validado['cargo'] ??= null;
        $validado['papel_setor'] = $validado['papel_setor'] !== null ? PapelSetor::from($validado['papel_setor']) : null;
        $validado['aprova_setores'] = array_values(array_diff(array_map('intval', $validado['aprova_setores'] ?? []), [(int) $validado['setor_id']]));

        // Mesmas regras da importação.
        $doEstoque = (int) $validado['setor_id'] === Setor::idDoEstoque();
        if ($validado['is_estoque'] && ! $doEstoque) {
            throw ValidationException::withMessages(['is_estoque' => 'A permissão de estoque é só para quem é do setor Estoque.']);
        }
        if ($validado['is_lider_estoque'] && ! $validado['is_estoque']) {
            throw ValidationException::withMessages(['is_lider_estoque' => 'Líder do estoque também precisa ter a permissão de estoque.']);
        }

        return $validado;
    }

    /**
     * @param  array<string, mixed>  $validado
     */
    private function protegerAdministradores(?User $alvo, array $validado, User $por): void
    {
        if ($alvo === null) {
            return;
        }

        if ($alvo->is($por) && (! $validado['is_admin'] || ! $validado['ativo'])) {
            throw ValidationException::withMessages([
                $validado['ativo'] ? 'is_admin' : 'ativo' => 'Você não pode tirar o seu próprio acesso. Peça para outro administrador.',
            ]);
        }

        $deixaDeSerAdmin = $alvo->is_admin && $alvo->ativo && (! $validado['is_admin'] || ! $validado['ativo']);
        $outrosAdmins = User::query()->where('is_admin', true)->where('ativo', true)->whereKeyNot($alvo->id)->exists();

        if ($deixaDeSerAdmin && ! $outrosAdmins) {
            throw ValidationException::withMessages(['is_admin' => 'O sistema precisa ter pelo menos um administrador ativo.']);
        }
    }

    /**
     * O papel no próprio setor e os outros setores que a pessoa aprova (como líder).
     */
    private function sincronizarAprovacoes(User $user, ?PapelSetor $papel, array $outros): void
    {
        $aprovacoes = [];

        if ($papel !== null) {
            $aprovacoes[$user->setor_id] = ['papel' => $papel];
        }
        foreach ($outros as $setorId) {
            $aprovacoes[$setorId] = ['papel' => PapelSetor::LIDER];
        }

        $user->setoresAprovados()->sync($aprovacoes);
    }

    /**
     * Como a pessoa está agora, em texto legível, para o histórico.
     *
     * @return array<string, string>
     */
    private function retrato(User $user): array
    {
        $user->loadMissing('setor');
        $aprova = $user->setoresAprovados()->orderBy('nome')->get();
        $doSetor = $aprova->firstWhere('id', $user->setor_id);

        $retrato = [
            'Nome' => $user->nome,
            'Login' => $user->login,
            'Matrícula' => (string) ($user->matricula ?? '—'),
            'Cargo' => $user->cargo ?? '—',
            'Setor' => $user->setor->nome,
            'Situação' => $user->ativo ? 'Ativo' : 'Desativado',
            'Papel no setor' => $doSetor ? $doSetor->pivot->papel->rotulo() : '—',
            'Também aprova' => $aprova->reject(fn (Setor $s) => $s->id === $user->setor_id)->pluck('nome')->join(', ') ?: '—',
        ];

        foreach (self::PERMISSOES as $campo => $rotulo) {
            $retrato[$rotulo] = $user->{$campo} ? 'Sim' : 'Não';
        }

        return $retrato;
    }

    /**
     * @param  array<string, string>  $antes
     * @param  array<string, string>  $depois
     * @return array<string, array{de: string, para: string}>
     */
    private function diferenca(array $antes, array $depois): array
    {
        $mudancas = [];

        foreach ($depois as $campo => $valor) {
            if (($antes[$campo] ?? null) !== $valor) {
                $mudancas[$campo] = ['de' => $antes[$campo] ?? '—', 'para' => $valor];
            }
        }

        return $mudancas;
    }

    private function registrar(User $alvo, User $por, string $acao, ?array $mudancas): void
    {
        UsuarioAlteracao::create([
            'usuario_id' => $alvo->id,
            'feito_por_id' => $por->id,
            'acao' => $acao,
            'mudancas' => $mudancas,
            'ip' => request()->ip(),
        ]);
    }
}
