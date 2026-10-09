<x-layouts.acesso titulo="Entrar">
    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <h1 class="titulo-pagina">Entrar</h1>
        <p class="mt-2 text-[13px] text-mid-gray">Use o usuário e a senha que a TI passou para você.</p>

        <div class="mt-7 space-y-4">
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

        <button type="submit" class="botao botao-primario mt-6 h-10 w-full">Entrar</button>
    </form>

    <p class="mt-5 text-center text-[13px] text-mid-gray dark:text-steel">Primeiro acesso ou esqueceu a senha? Procure a TI.</p>
</x-layouts.acesso>
