<x-layouts.acesso titulo="Entrar">
    <h1 class="text-xl font-semibold text-slate-900">Entrar</h1>
    <p class="mt-1 text-sm text-slate-600">Use o usuário e a senha que você recebeu da TI.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf

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

        <button type="submit" class="botao botao-primario w-full">Entrar</button>
    </form>

    <p class="mt-6 text-xs text-slate-600">Esqueceu a senha? Peça para a TI gerar uma senha provisória.</p>
</x-layouts.acesso>
