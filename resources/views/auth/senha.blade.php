<x-layouts.acesso titulo="Definir senha">
    <h1 class="text-xl font-semibold text-slate-900">{{ $obrigatoria ? 'Defina sua senha' : 'Trocar senha' }}</h1>
    <p class="mt-1 text-sm text-slate-600">
        @if ($obrigatoria)
            Você entrou com uma senha provisória. Crie a sua para continuar. Ela também é usada para assinar as requisições.
        @else
            A senha também é usada para assinar as requisições. Não compartilhe com ninguém.
        @endif
    </p>

    <form method="POST" action="{{ route('senha.update') }}" class="mt-6 space-y-4">
        @csrf
        @method('PUT')

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

        <button type="submit" class="botao botao-primario w-full">Salvar senha</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4">
        @csrf
        <button type="submit" class="link cursor-pointer text-sm">Sair</button>
    </form>
</x-layouts.acesso>
