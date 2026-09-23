<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'login' => ['required', 'string', 'max:60'],
            'senha' => ['required', 'string'],
        ], [], ['login' => 'usuário', 'senha' => 'senha']);

        $credenciais = [
            'login' => mb_strtolower(trim($dados['login'])),
            'password' => $dados['senha'],
            'ativo' => true,
        ];

        // Mesma mensagem para usuário inexistente, inativo ou senha errada.
        if (! Auth::attempt($credenciais)) {
            throw ValidationException::withMessages(['login' => 'Usuário ou senha incorretos.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
