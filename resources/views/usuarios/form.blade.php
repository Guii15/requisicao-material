<x-layouts.app :titulo="$usuario ? $usuario->nome : 'Novo login'">
    @php
        $valor = fn (string $campo, mixed $padrao = null) => old($campo, $padrao);
        $marcado = fn (string $campo) => (bool) old($campo, $usuario?->{$campo} ?? false);
        $papelAtual = $usuario?->setoresAprovados->firstWhere('id', $usuario->setor_id)?->pivot->papel->value;
        $outrosAtuais = $usuario
            ? $usuario->setoresAprovados->reject(fn ($s) => $s->id === $usuario->setor_id)->pluck('id')->all()
            : [];
        $outrosMarcados = array_map('intval', (array) old('aprova_setores', $outrosAtuais));
    @endphp

    <a href="{{ route('usuarios.index') }}" class="-ml-2 inline-flex h-8 items-center gap-1.5 rounded-full px-2 text-sm font-medium text-mid-gray transition-colors hover:bg-hairline hover:text-ink">
        <x-phosphor-arrow-left class="size-4" aria-hidden="true" />Voltar para Usuários
    </a>

    <x-cabecalho :titulo="$usuario ? $usuario->nome : 'Novo login'"
                 :subtitulo="$usuario ? 'Login '.$usuario->login : 'A pessoa entra com a senha inicial e o sistema pede para ela criar a dela no primeiro acesso.'" />

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <form method="POST" action="{{ $usuario ? route('usuarios.update', $usuario) : route('usuarios.store') }}" class="space-y-7">
            @csrf
            @if ($usuario)
                @method('PUT')
            @endif

            <section class="quadro p-5" aria-labelledby="dados-titulo">
                <h2 id="dados-titulo" class="mb-4 font-display text-[14px] font-bold text-ink">Dados</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="nome" class="rotulo">Nome completo</label>
                        <input id="nome" name="nome" required maxlength="255" value="{{ $valor('nome', $usuario?->nome) }}" class="campo mt-1.5 @error('nome') campo-erro @enderror">
                        @error('nome') <p class="erro-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="login" class="rotulo">Login</label>
                        <p class="ajuda">Letras minúsculas e ponto, como geisla.luiz.</p>
                        <input id="login" name="login" required maxlength="60" autocomplete="off" value="{{ $valor('login', $usuario?->login) }}" class="campo mt-1.5 lowercase @error('login') campo-erro @enderror">
                        @error('login') <p class="erro-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="matricula" class="rotulo">Matrícula (opcional)</label>
                        <p class="ajuda">A do WinThor, se tiver.</p>
                        <input id="matricula" name="matricula" inputmode="numeric" value="{{ $valor('matricula', $usuario?->matricula) }}" class="campo mt-1.5 tabular-nums @error('matricula') campo-erro @enderror">
                        @error('matricula') <p class="erro-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cargo" class="rotulo">Cargo (opcional)</label>
                        <input id="cargo" name="cargo" maxlength="100" value="{{ $valor('cargo', $usuario?->cargo) }}" class="campo mt-1.5 @error('cargo') campo-erro @enderror">
                        @error('cargo') <p class="erro-campo">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="setor_id" class="rotulo">Setor</label>
                        <select id="setor_id" name="setor_id" required class="campo mt-1.5 @error('setor_id') campo-erro @enderror">
                            <option value="">Escolha…</option>
                            @foreach ($setores as $setor)
                                <option value="{{ $setor->id }}" @selected((int) $valor('setor_id', $usuario?->setor_id) === $setor->id)>{{ \App\Support\Texto::setor($setor->nome) }}</option>
                            @endforeach
                        </select>
                        @error('setor_id') <p class="erro-campo">{{ $message }}</p> @enderror
                    </div>
                    @unless ($usuario)
                        <div class="sm:col-span-2">
                            <label for="senha" class="rotulo">Senha inicial</label>
                            <p class="ajuda">Pelo menos 8 caracteres, com letras e números. Passe para a pessoa; ela troca no primeiro acesso.</p>
                            <input id="senha" name="senha" type="text" required autocomplete="new-password" class="campo mt-1.5 sm:w-72 @error('senha') campo-erro @enderror">
                            @error('senha') <p class="erro-campo">{{ $message }}</p> @enderror
                        </div>
                    @endunless
                </div>
            </section>

            <section class="quadro p-5" aria-labelledby="aprovacao-titulo">
                <h2 id="aprovacao-titulo" class="mb-1 font-display text-[14px] font-bold text-ink">Aprovação de requisições</h2>
                <p class="ajuda mb-4">Quem aprova os pedidos de Teste e Uso e Consumo. Líder e sublíder valem igual.</p>
                <fieldset>
                    <legend class="rotulo">No próprio setor</legend>
                    <div class="mt-2 flex flex-wrap gap-3">
                        @foreach (['' => 'Não aprova', 'LIDER' => 'Líder', 'SUBLIDER' => 'Sublíder'] as $papel => $rotulo)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-hairline px-3.5 py-2.5 text-[13px] has-[:checked]:border-marca has-[:checked]:bg-marca-suave">
                                <input type="radio" name="papel_setor" value="{{ $papel }}" class="accent-marca" @checked((string) $valor('papel_setor', $papelAtual ?? '') === $papel)>
                                {{ $rotulo }}
                            </label>
                        @endforeach
                    </div>
                    @error('papel_setor') <p class="erro-campo">{{ $message }}</p> @enderror
                </fieldset>
                <fieldset class="mt-5">
                    <legend class="rotulo">Também aprova estes setores</legend>
                    <p class="ajuda">Ex.: os líderes do estoque aprovam Showroom e Ninja Place.</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        @foreach ($setores as $setor)
                            <label class="flex cursor-pointer items-center gap-2 text-[13px]">
                                <input type="checkbox" name="aprova_setores[]" value="{{ $setor->id }}" class="accent-marca" @checked(in_array($setor->id, $outrosMarcados, true))>
                                {{ \App\Support\Texto::setor($setor->nome) }}
                            </label>
                        @endforeach
                    </div>
                    @error('aprova_setores') <p class="erro-campo">{{ $message }}</p> @enderror
                </fieldset>
            </section>

            <section class="quadro p-5" aria-labelledby="permissoes-titulo">
                <h2 id="permissoes-titulo" class="mb-4 font-display text-[14px] font-bold text-ink">Permissões</h2>
                <div class="space-y-3">
                    @foreach (\App\Services\GestaoUsuarios::PERMISSOES as $campo => $rotulo)
                        <div>
                            <label class="flex cursor-pointer items-start gap-2.5 text-[13px]">
                                <input type="hidden" name="{{ $campo }}" value="0">
                                <input type="checkbox" name="{{ $campo }}" value="1" class="mt-0.5 accent-marca" @checked($marcado($campo))>
                                <span>{{ $rotulo }}</span>
                            </label>
                            @error($campo) <p class="erro-campo">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="quadro p-5" aria-labelledby="situacao-titulo">
                <h2 id="situacao-titulo" class="mb-3 font-display text-[14px] font-bold text-ink">Situação</h2>
                <input type="hidden" name="ativo" value="0">
                <label class="flex cursor-pointer items-start gap-2.5 text-[13px]">
                    <input type="checkbox" name="ativo" value="1" class="mt-0.5 accent-marca" @checked((bool) old('ativo', $usuario?->ativo ?? true))>
                    <span>Ativo. Desmarque para bloquear o acesso na hora (o histórico das requisições continua).</span>
                </label>
                @error('ativo') <p class="erro-campo">{{ $message }}</p> @enderror
            </section>

            <div class="flex gap-3">
                <button type="submit" class="botao botao-primario">{{ $usuario ? 'Salvar alterações' : 'Criar login' }}</button>
                <a href="{{ route('usuarios.index') }}" class="botao botao-secundario">Cancelar</a>
            </div>
        </form>

        @if ($usuario)
            <aside class="space-y-6">
                <section class="quadro p-5" aria-labelledby="senha-titulo">
                    <h2 id="senha-titulo" class="mb-1 font-display text-[14px] font-bold text-ink">Resetar senha</h2>
                    <p class="ajuda mb-3">Para quem esqueceu a senha. A pessoa entra com esta e cria uma nova.</p>
                    <form method="POST" action="{{ route('usuarios.resetar-senha', $usuario) }}">
                        @csrf
                        <label for="senha-provisoria" class="rotulo">Senha provisória</label>
                        <input id="senha-provisoria" name="senha" type="text" required autocomplete="new-password" class="campo mt-1.5 @error('senha') campo-erro @enderror">
                        @error('senha') <p class="erro-campo">{{ $message }}</p> @enderror
                        <button type="submit" class="botao botao-secundario mt-3 w-full">Resetar senha</button>
                    </form>
                </section>

                <section class="quadro" aria-labelledby="historico-titulo">
                    <h2 id="historico-titulo" class="quadro-titulo">Histórico</h2>
                    @if ($historico->isEmpty())
                        <p class="p-5 text-[13px] text-mid-gray">Nenhuma alteração feita pela tela ainda.</p>
                    @else
                        <ol class="divide-y divide-hairline">
                            @foreach ($historico as $alteracao)
                                <li class="px-5 py-3 text-[13px]">
                                    <p class="font-medium text-ink">{{ $alteracao->rotulo() }}</p>
                                    <p class="text-mid-gray">{{ $alteracao->feitoPor?->nome }}, {{ $alteracao->created_at->format('d/m/Y \à\s H:i') }}</p>
                                    @if ($alteracao->acao === 'ALTERACAO' && is_array($alteracao->mudancas))
                                        <ul class="mt-1 space-y-0.5 text-ink-soft">
                                            @foreach ($alteracao->mudancas as $campo => $mudanca)
                                                <li>{{ $campo }}: {{ $mudanca['de'] ?? '—' }} → {{ $mudanca['para'] ?? '—' }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </aside>
        @endif
    </div>
</x-layouts.app>
