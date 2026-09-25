<x-layouts.acesso titulo="Entrar">
    <form method="POST" action="{{ route('login.store') }}" class="quadro">
        @csrf
        <div class="px-6 pt-6">
            <h1 class="text-xl font-semibold tracking-tight">Entrar</h1>
            <p class="mt-1 text-sm text-mid-gray">Requisição de material do estoque.</p>
        </div>

        <div class="space-y-4 px-6 py-5">
            <div>
                <label for="login" class="rotulo">Usuário</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}"
                       autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus
                       @error('login') aria-invalid="true" aria-describedby="login-erro" @enderror
                       class="campo mt-2 @error('login') campo-erro @enderror">
                @error('login')
                    <p id="login-erro" class="erro-campo">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="senha" class="rotulo">Senha</label>
                <input id="senha" name="senha" type="password" autocomplete="current-password" required
                       @error('senha') aria-invalid="true" aria-describedby="senha-erro" @enderror
                       class="campo mt-2 @error('senha') campo-erro @enderror">
                @error('senha')
                    <p id="senha-erro" class="erro-campo">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="px-6 pb-6">
            <button type="submit" class="botao botao-primario w-full">Entrar</button>
        </div>
    </form>

    <p class="mt-4 text-center text-sm text-white/70">Primeiro acesso ou esqueceu a senha: procure a TI.</p>
</x-layouts.acesso>
