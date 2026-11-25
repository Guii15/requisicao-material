<x-layouts.app titulo="Usuários e permissões">
    @php
        $selos = [
            'is_admin' => 'Admin',
            'is_lider_estoque' => 'Líder do estoque',
            'is_estoque' => 'Estoque',
            'is_responsavel_baixa' => 'Baixa',
            'aprova_compras' => 'Aprova compras',
            'faz_transferencia' => 'Transferência',
        ];
    @endphp

    <x-cabecalho titulo="Usuários e permissões" subtitulo="Crie logins, dê ou tire permissões e resete senhas. Tudo fica registrado no histórico de cada pessoa.">
        <div class="flex flex-wrap items-center gap-2">
        <form method="POST" action="{{ route('usuarios.exigir-troca-senha') }}"
              onsubmit="return confirm('Quem ainda entra com a senha Bin@2026 vai ter que criar a própria senha no próximo acesso. Continuar?')">
            @csrf
            <button type="submit" class="botao botao-secundario">Pedir nova senha no 1º acesso</button>
        </form>
        <a href="{{ route('usuarios.create') }}" class="botao botao-primario">
            <x-phosphor-user-plus class="size-[18px]" aria-hidden="true" />Novo login
        </a>
        </div>
    </x-cabecalho>

    <form method="GET" action="{{ route('usuarios.index') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <div class="min-w-56 flex-1">
            <label for="busca" class="rotulo">Buscar</label>
            <input type="search" id="busca" name="busca" value="{{ $busca }}" placeholder="Nome, login ou matrícula" class="campo mt-1.5">
        </div>
        <div>
            <label for="setor" class="rotulo">Setor</label>
            <select id="setor" name="setor" class="campo mt-1.5 sm:w-56">
                <option value="">Todos</option>
                @foreach ($setores as $setor)
                    <option value="{{ $setor->id }}" @selected($setorId === $setor->id)>{{ \App\Support\Texto::setor($setor->nome) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="situacao" class="rotulo">Situação</label>
            <select id="situacao" name="situacao" class="campo mt-1.5 sm:w-40">
                <option value="ativos" @selected($situacao === 'ativos')>Ativos</option>
                <option value="desativados" @selected($situacao === 'desativados')>Desativados</option>
                <option value="todos" @selected($situacao === 'todos')>Todos</option>
            </select>
        </div>
        <button type="submit" class="botao botao-secundario">Filtrar</button>
    </form>

    <section class="quadro" aria-label="Usuários">
        @if ($usuarios->isEmpty())
            <p class="p-8 text-center text-sm text-mid-gray">Ninguém encontrado com esse filtro.</p>
        @else
            {{-- Celular: um cartão por pessoa. A tabela de 7 colunas só cabe do tablet para cima. --}}
            <ul class="divide-y divide-hairline md:hidden dark:divide-line-escuro">
                @foreach ($usuarios as $usuario)
                    <li class="space-y-2.5 px-4 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('usuarios.edit', $usuario) }}" class="block font-medium text-ink hover:underline">{{ $usuario->nome }}</a>
                                <span class="block text-[12px] text-mid-gray">{{ $usuario->login }} · {{ \App\Support\Texto::setor($usuario->setor->nome) }}</span>
                            </div>
                            @if (! $usuario->ativo)
                                <span class="selo selo-erro shrink-0">Desativado</span>
                            @elseif ($usuario->deve_trocar_senha)
                                <span class="selo selo-pendente shrink-0">Falta trocar a senha</span>
                            @else
                                <span class="selo selo-ok shrink-0">Ativo</span>
                            @endif
                        </div>
                        @php
                            $aprova = $usuario->setoresAprovados->map(fn ($s) => \App\Support\Texto::setor($s->nome).($s->id === $usuario->setor_id ? ' ('.mb_strtolower($s->pivot->papel->rotulo()).')' : ''))->join(', ');
                        @endphp
                        @if ($aprova)
                            <p class="text-[12px] text-mid-gray">Aprova: <span class="text-ink">{{ $aprova }}</span></p>
                        @endif
                        <span class="flex flex-wrap gap-1">
                            @foreach ($selos as $campo => $rotulo)
                                @if ($usuario->{$campo})
                                    <span class="selo {{ $campo === 'is_admin' ? 'selo-info' : 'selo-neutro' }}">{{ $rotulo }}</span>
                                @endif
                            @endforeach
                        </span>
                        <a href="{{ route('usuarios.edit', $usuario) }}" class="botao botao-secundario w-full">Editar / senha</a>
                    </li>
                @endforeach
            </ul>

            <div class="hidden overflow-x-auto md:block">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th scope="col">Nome</th>
                            <th scope="col">Login</th>
                            <th scope="col">Setor</th>
                            <th scope="col">Aprova</th>
                            <th scope="col">Permissões</th>
                            <th scope="col">Situação</th>
                            <th scope="col"><span class="sr-only">Ações</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuarios as $usuario)
                            <tr>
                                <td>
                                    <a href="{{ route('usuarios.edit', $usuario) }}" class="font-medium text-ink hover:underline">{{ $usuario->nome }}</a>
                                    @if ($usuario->cargo)
                                        <span class="block text-[12px] text-mid-gray">{{ $usuario->cargo }}</span>
                                    @endif
                                </td>
                                <td class="tabular-nums">{{ $usuario->login }}</td>
                                <td>{{ \App\Support\Texto::setor($usuario->setor->nome) }}</td>
                                <td class="text-[13px]">
                                    {{ $usuario->setoresAprovados->map(fn ($s) => \App\Support\Texto::setor($s->nome).($s->id === $usuario->setor_id ? ' ('.mb_strtolower($s->pivot->papel->rotulo()).')' : ''))->join(', ') ?: '—' }}
                                </td>
                                <td>
                                    <span class="flex flex-wrap gap-1">
                                        @foreach ($selos as $campo => $rotulo)
                                            @if ($usuario->{$campo})
                                                <span class="selo {{ $campo === 'is_admin' ? 'selo-info' : 'selo-neutro' }}">{{ $rotulo }}</span>
                                            @endif
                                        @endforeach
                                    </span>
                                </td>
                                <td>
                                    @if (! $usuario->ativo)
                                        <span class="selo selo-erro">Desativado</span>
                                    @elseif ($usuario->deve_trocar_senha)
                                        <span class="selo selo-pendente">Falta trocar a senha</span>
                                    @else
                                        <span class="selo selo-ok">Ativo</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('usuarios.edit', $usuario) }}" class="botao botao-secundario whitespace-nowrap">Editar / senha</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="mt-4">
        <x-paginacao :paginator="$usuarios" />
    </div>
</x-layouts.app>
