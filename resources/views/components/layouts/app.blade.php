@props(['titulo', 'secao' => null])
@php
    // Item do menu marcado: a página pode informar (detalhe da requisição); senão, vale a rota.
    $secao ??= match (true) {
        request()->routeIs('painel.*') => 'painel',
        request()->routeIs('requisicoes.index') => 'minhas',
        request()->routeIs('requisicoes.create') => 'nova',
        request()->routeIs('aprovacoes.*') => 'aprovacoes',
        request()->routeIs('separacao.*') => 'separacao',
        request()->routeIs('liberacao.*') => 'liberacao',
        request()->routeIs('entrega.*') => 'entrega',
        request()->routeIs('devolucao.*') => 'devolucao',
        request()->routeIs('baixa.*') => 'baixa',
        request()->routeIs('compras.*') => 'compras',
        default => null,
    };

    $temFluxo = collect($menu)->contains(fn ($contador) => $contador !== null);
    $setorDoUsuario = \App\Support\Texto::setor(auth()->user()->setor->nome);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('imagens/favicon.png') }}">
    <script>
        if (localStorage.getItem('darkMode') === '1') document.documentElement.classList.add('dark');
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-fundo" x-data="{ menu: false }" x-on:keydown.escape.window="menu = false">
    {{-- Celular e tablet: o menu abre por cima, com a página escurecida atrás. --}}
    <div x-show="menu" x-cloak x-on:click="menu = false" class="fixed inset-0 z-20 bg-ink/35 lg:hidden" aria-hidden="true"></div>

    <aside class="fixed inset-y-0 left-0 z-30 flex w-[236px] flex-col overflow-y-auto bg-preto px-4 pt-7 pb-4 transition-transform duration-200 max-lg:-translate-x-full"
           x-bind:style="menu ? 'translate: 0 0' : ''">
        <a href="{{ route('inicio') }}" class="mb-7 ml-3 flex h-9 shrink-0 items-center focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marca">
            <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-8 w-auto">
        </a>

        <div class="mx-1 mb-7 flex items-center gap-2.5 border-y border-preto-linha px-1.5 py-3.5">
            <span class="rounded-md bg-white p-[9px] text-marca"><x-phosphor-package class="size-[17px]" aria-hidden="true" /></span>
            <div class="min-w-0">
                <strong class="block truncate text-[12px] font-semibold text-white">Requisição de material</strong>
                <small class="mt-0.5 block text-[11px] text-preto-texto">Gestão interna</small>
            </div>
        </div>

        <nav aria-label="Menu principal">
            <p class="etiqueta mb-2.5 px-3.5 !text-preto-texto">Principal</p>
            <x-menu-link-lateral :href="route('painel.index')" :ativo="$secao === 'painel'" icone="squares-four">Painel</x-menu-link-lateral>
            <x-menu-link-lateral :href="route('requisicoes.index')" :ativo="$secao === 'minhas'" icone="clipboard-text">Minhas requisições</x-menu-link-lateral>
            <x-menu-link-lateral :href="route('requisicoes.create')" :ativo="$secao === 'nova'" icone="plus">Nova requisição</x-menu-link-lateral>

            @if ($temFluxo)
                <p class="etiqueta mt-7 mb-2.5 px-3.5 !text-preto-texto">Fluxo do estoque</p>
            @endif
            @if ($menu['aprovacoes'] !== null)
                <x-menu-link-lateral :href="route('aprovacoes.index')" :ativo="$secao === 'aprovacoes'" :contador="$menu['aprovacoes']" contador-nome="aprovacoes" icone="shield-check">Aprovações</x-menu-link-lateral>
            @endif
            @if ($menu['separacao'] !== null)
                <x-menu-link-lateral :href="route('separacao.index')" :ativo="$secao === 'separacao'" :contador="$menu['separacao']" contador-nome="separacao" icone="cube">Separação</x-menu-link-lateral>
            @endif
            @if ($menu['liberacao'] !== null)
                <x-menu-link-lateral :href="route('liberacao.index')" :ativo="$secao === 'liberacao'" :contador="$menu['liberacao']" contador-nome="liberacao" icone="lock-key">Liberação</x-menu-link-lateral>
            @endif
            @if ($menu['entrega'] !== null)
                <x-menu-link-lateral :href="route('entrega.index')" :ativo="$secao === 'entrega'" :contador="$menu['entrega']" contador-nome="entrega" icone="truck">Entrega</x-menu-link-lateral>
            @endif
            @if ($menu['devolucao'] !== null)
                <x-menu-link-lateral :href="route('devolucao.index')" :ativo="$secao === 'devolucao'" :contador="$menu['devolucao']" contador-nome="devolucao" icone="arrow-counter-clockwise">Devolução</x-menu-link-lateral>
            @endif
            @if ($menu['compras'] !== null)
                <x-menu-link-lateral :href="route('compras.index')" :ativo="$secao === 'compras'" :contador="$menu['compras']" contador-nome="compras" icone="shopping-cart">Compras de funcionários</x-menu-link-lateral>
            @endif
            @if ($menu['baixa'] !== null)
                <x-menu-link-lateral :href="route('baixa.index')" :ativo="$secao === 'baixa'" :contador="$menu['baixa']" contador-nome="baixa" icone="list-checks">Baixa</x-menu-link-lateral>
            @endif
        </nav>

        <div class="mt-auto pt-7">
            <a href="https://wa.me/553598998455" target="_blank" rel="noopener noreferrer" class="flex items-center gap-2.5 rounded-md px-3 py-4 text-[12px] text-preto-texto transition-colors hover:text-white focus-visible:outline-2 focus-visible:outline-marca">
                <x-phosphor-headphones class="size-[17px]" aria-hidden="true" />
                <span>Precisa de ajuda?</span>
                <x-phosphor-arrow-up-right class="ml-auto size-3.5" aria-hidden="true" />
            </a>
            <div class="flex items-center gap-2.5 border-t border-preto-linha px-1.5 pt-4">
                <x-avatar :nome="auth()->user()->nome" class="!bg-white !text-marca" />
                <div class="min-w-0 flex-1">
                    <strong class="block truncate text-[12px] font-semibold text-white">{{ auth()->user()->nome }}</strong>
                    <small class="mt-0.5 block truncate text-[11px] text-preto-texto">{{ $setorDoUsuario }}</small>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <button type="submit" title="Sair" class="inline-flex h-8 cursor-pointer items-center rounded-md px-2 text-[12px] font-medium text-preto-texto transition-colors hover:bg-preto-2 hover:text-white focus-visible:outline-2 focus-visible:outline-marca">Sair</button>
                </form>
            </div>
        </div>
    </aside>

    <div class="lg:pl-[236px]">
        <header class="sticky top-0 z-10 flex h-[61px] items-center justify-between border-b border-hairline bg-paper px-5 lg:h-[73px] lg:px-8 xl:px-11">
            <div class="flex min-w-0 items-center gap-3.5 text-[12px] text-mid-gray">
                <button type="button" class="-ml-2 inline-flex size-9 items-center justify-center rounded-md text-ink hover:bg-surface-alt focus-visible:outline-2 focus-visible:outline-marca lg:hidden" aria-label="Abrir menu" x-on:click="menu = true">
                    <x-phosphor-list class="size-5" aria-hidden="true" />
                </button>
                <span class="hidden sm:inline">Requisição de material</span>
                <x-phosphor-caret-right class="hidden size-3.5 shrink-0 sm:block" aria-hidden="true" />
                <strong class="truncate font-medium text-ink">{{ $titulo }}</strong>
            </div>
            <x-avatar :nome="auth()->user()->nome" class="size-[29px] text-[10px]" />
        </header>

        <main class="mx-auto w-full max-w-[1600px] px-5 pt-6 lg:px-8 lg:pt-8 xl:px-11">
            <x-avisos />
            {{ $slot }}

            <footer class="mt-3 flex flex-col gap-1.5 py-6 text-[11px] text-mid-gray sm:flex-row sm:items-center sm:justify-between">
                <span>© {{ now()->year }} Binário Tecnologia</span>
                <span class="flex items-center gap-2.5">
                    Suporte TI
                    <a href="https://wa.me/553598998455" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-medium text-ink tabular-nums hover:underline">+55 35 9899-8455 <x-phosphor-arrow-up-right class="size-3" aria-hidden="true" /></a>
                </span>
            </footer>
        </main>
    </div>

    @stack('modais')
</body>
</html>
