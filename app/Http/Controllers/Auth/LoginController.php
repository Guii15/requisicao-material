<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Senhas erradas aceitas de um mesmo IP a cada 10 minutos, somando todos os logins. */
    private const ERROS_POR_IP = 30;

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

        // Só os erros contam: quem testa a senha padrão da importação em vários logins é barrado,
        // e a empresa inteira entrando pelo mesmo IP de manhã não é.
        $errosDoIp = 'login-erros|'.$request->ip();

        if (RateLimiter::tooManyAttempts($errosDoIp, self::ERROS_POR_IP)) {
            abort(429, 'Muitas tentativas erradas. Espere alguns minutos e tente de novo.');
        }

        // Mesma mensagem para usuário inexistente, inativo ou senha errada.
        if (! Auth::attempt($credenciais)) {
            RateLimiter::hit($errosDoIp, 10 * 60);

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
