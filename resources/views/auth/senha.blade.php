<x-layouts.acesso titulo="Definir senha">
    <form method="POST" action="{{ route('senha.update') }}" class="quadro">
        @csrf
        @method('PUT')
        <h1 class="quadro-titulo">{{ $obrigatoria ? 'Defina sua senha' : 'Trocar senha' }}</h1>

        <div class="space-y-4 px-4 py-4">
            <p class="text-sm text-slate-700">
                @if ($obrigatoria)
                    Você entrou com uma senha provisória. Crie a sua para continuar. Ela também assina as suas requisições.
                @else
                    A senha também assina as suas requisições. Não passe para ninguém.
                @endif
            </p>

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

        <div class="border-t border-slate-300 bg-slate-50 px-4 py-3">
            <button type="submit" class="botao botao-primario w-full">Salvar senha</button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="link cursor-pointer text-sm">Sair</button>
    </form>
</x-layouts.acesso>
