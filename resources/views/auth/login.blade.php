<x-layouts.acesso titulo="Entrar">
    <form method="POST" action="{{ route('login.store') }}" class="quadro">
        @csrf
        <h1 class="quadro-titulo">Entrar</h1>

        <div class="space-y-4 px-4 py-4">
            <div>
                <label for="login" class="rotulo">Usuário</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}"
                       autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus
                       @error('login') aria-invalid="true" aria-describedby="login-erro" @enderror
                       class="campo mt-1.5 @error('login') campo-erro @enderror">
                @error('login')
                    <p id="login-erro" class="erro-campo">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="senha" class="rotulo">Senha</label>
                <input id="senha" name="senha" type="password" autocomplete="current-password" required
                       @error('senha') aria-invalid="true" aria-describedby="senha-erro" @enderror
                       class="campo mt-1.5 @error('senha') campo-erro @enderror">
                @error('senha')
                    <p id="senha-erro" class="erro-campo">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="border-t border-slate-300 bg-slate-50 px-4 py-3">
            <button type="submit" class="botao botao-primario w-full">Entrar</button>
        </div>
    </form>

    <p class="mt-3 text-xs text-slate-600">Primeiro acesso ou esqueceu a senha: procure a TI.</p>
</x-layouts.acesso>
