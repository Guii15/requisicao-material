<x-layouts.acesso titulo="Definir senha">
    <form method="POST" action="{{ route('senha.update') }}">
        @csrf
        @method('PUT')
        <h1 class="font-display text-2xl leading-8 font-bold text-ink dark:text-bone">{{ $obrigatoria ? 'Defina sua senha' : 'Trocar senha' }}</h1>
        <p class="mt-1 text-sm text-mid-gray dark:text-steel">
            @if ($obrigatoria)
                Você entrou com uma senha provisória. Crie a sua para continuar. Ela também assina as suas requisições.
            @else
                A senha também assina as suas requisições. Não passe para ninguém.
            @endif
        </p>

        <div class="mt-6 space-y-4">
            <div>
                <label for="senha_atual" class="rotulo">{{ $obrigatoria ? 'Senha provisória' : 'Senha atual' }}</label>
                <input id="senha_atual" name="senha_atual" type="password" autocomplete="current-password" required autofocus
                       class="campo mt-1.5 @error('senha_atual') campo-erro @enderror">
                @error('senha_atual')
                    <p class="erro-campo">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="nova_senha" class="rotulo">Nova senha</label>
                <p class="ajuda">Pelo menos 8 caracteres, com letras e números.</p>
                <input id="nova_senha" name="nova_senha" type="password" autocomplete="new-password" required
                       class="campo mt-1.5 @error('nova_senha') campo-erro @enderror">
                @error('nova_senha')
                    <p class="erro-campo">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="nova_senha_confirmation" class="rotulo">Repita a nova senha</label>
                <input id="nova_senha_confirmation" name="nova_senha_confirmation" type="password" autocomplete="new-password" required
                       class="campo mt-1.5">
            </div>
        </div>

        <button type="submit" class="botao botao-primario mt-6 h-10 w-full">Salvar senha</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="cursor-pointer text-[13px] font-medium text-mid-gray underline underline-offset-[3px] hover:text-ink">Sair</button>
    </form>
</x-layouts.acesso>
