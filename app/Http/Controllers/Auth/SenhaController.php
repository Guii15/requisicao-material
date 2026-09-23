<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SenhaController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.senha', ['obrigatoria' => $request->user()->deve_trocar_senha]);
    }

    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'senha_atual' => ['required', 'string', 'current_password'],
            'nova_senha' => ['required', 'string', 'confirmed', 'different:senha_atual', Password::min(8)->letters()->numbers()],
        ], [
            'nova_senha.different' => 'A nova senha precisa ser diferente da atual.',
        ], [
            'senha_atual' => 'senha atual',
            'nova_senha' => 'nova senha',
        ]);

        $request->user()->forceFill([
            'password' => $dados['nova_senha'],
            'deve_trocar_senha' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()->route('inicio')->with('sucesso', 'Senha alterada.');
    }
}
