<?php

namespace App\Http\Controllers;

use App\Models\Setor;
use App\Models\User;
use App\Models\UsuarioAlteracao;
use App\Services\GestaoUsuarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Tela Usuários (só administradores): criar login, dar e tirar permissões, resetar senha e
 * desativar, sem mexer direto no banco.
 */
class UsuarioController extends Controller
{
    private const CAMPOS = ['nome', 'login', 'matricula', 'cargo', 'setor_id', 'papel_setor', 'aprova_setores', 'senha', 'ativo', 'is_estoque', 'is_lider_estoque', 'is_responsavel_baixa', 'aprova_compras', 'faz_transferencia', 'is_admin'];

    public function index(Request $request): View
    {
        Gate::authorize('gerenciar-usuarios');

        $busca = trim((string) $this->texto($request, 'busca'));
        $setorId = (int) $request->integer('setor');
        $situacao = in_array($request->query('situacao'), ['ativos', 'desativados', 'todos'], true) ? $request->query('situacao') : 'ativos';

        $usuarios = User::query()
            ->with(['setor', 'setoresAprovados'])
            ->when($busca !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('nome', 'like', "%{$busca}%")
                ->orWhere('login', 'like', "%{$busca}%")
                ->orWhere('matricula', $busca)))
            ->when($setorId > 0, fn ($q) => $q->where('setor_id', $setorId))
            ->when($situacao !== 'todos', fn ($q) => $q->where('ativo', $situacao === 'ativos'))
            ->orderBy('nome')
            ->paginate(50)
            ->withQueryString();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'setores' => Setor::query()->orderBy('nome')->get(),
            'busca' => $busca,
            'setorId' => $setorId,
            'situacao' => $situacao,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('gerenciar-usuarios');

        return view('usuarios.form', [
            'usuario' => null,
            'setores' => Setor::query()->where('ativo', true)->orderBy('nome')->get(),
            'historico' => collect(),
        ]);
    }

    public function store(Request $request, GestaoUsuarios $gestao): RedirectResponse
    {
        $usuario = $gestao->salvar(null, $request->only(self::CAMPOS), $request->user());

        return redirect()->route('usuarios.edit', $usuario)
            ->with('sucesso', "Login {$usuario->login} criado. Passe a senha inicial para a pessoa: o sistema pede a troca no primeiro acesso.");
    }

    public function edit(User $usuario): View
    {
        Gate::authorize('gerenciar-usuarios');

        return view('usuarios.form', [
            'usuario' => $usuario->load(['setor', 'setoresAprovados']),
            'setores' => Setor::query()->where(fn ($q) => $q->where('ativo', true)->orWhere('id', $usuario->setor_id))->orderBy('nome')->get(),
            'historico' => UsuarioAlteracao::query()->with('feitoPor')->where('usuario_id', $usuario->id)->latest('id')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, User $usuario, GestaoUsuarios $gestao): RedirectResponse
    {
        $gestao->salvar($usuario, $request->only(self::CAMPOS), $request->user());

        return redirect()->route('usuarios.edit', $usuario)->with('sucesso', 'Alterações salvas.');
    }

    public function resetarSenha(Request $request, User $usuario, GestaoUsuarios $gestao): RedirectResponse
    {
        $gestao->resetarSenha($usuario, $this->texto($request, 'senha'), $request->user());

        return redirect()->route('usuarios.edit', $usuario)
            ->with('sucesso', "Senha de {$usuario->login} resetada. A pessoa troca no próximo acesso.");
    }
}
