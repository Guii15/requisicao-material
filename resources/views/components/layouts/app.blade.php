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
        default => null,
    };
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
<body class="min-h-dvh bg-fundo">
    {{-- Celular e tablet: barra no topo, com o menu rolando na horizontal. --}}
    <header class="sticky top-0 z-30 border-b border-hairline bg-barra lg:hidden">
        <div class="flex flex-wrap items-center gap-x-4 px-4 sm:gap-x-6 sm:px-6">
            <a href="{{ route('inicio') }}" class="flex h-14 shrink-0 items-center focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ink">
                <img src="{{ asset('imagens/logo-escuro.png') }}" alt="Binário Tecnologia" class="h-6 w-auto">
            </a>

            <nav class="order-last -mx-1 flex w-full min-w-0 items-center gap-1 overflow-x-auto border-t border-hairline py-2" aria-label="Menu principal">
                <x-menu-link :href="route('painel.index')" :ativo="$secao === 'painel'">Painel</x-menu-link>
                <x-menu-link :href="route('requisicoes.index')" :ativo="$secao === 'minhas'">Minhas</x-menu-link>
                <x-menu-link :href="route('requisicoes.create')" :ativo="$secao === 'nova'">Nova</x-menu-link>
                @if ($menu['aprovacoes'] !== null)
                    <x-menu-link :href="route('aprovacoes.index')" :ativo="$secao === 'aprovacoes'" :contador="$menu['aprovacoes']" contador-nome="aprovacoes">Aprovações</x-menu-link>
                @endif
                @if ($menu['separacao'] !== null)
                    <x-menu-link :href="route('separacao.index')" :ativo="$secao === 'separacao'" :contador="$menu['separacao']" contador-nome="separacao">Separação</x-menu-link>
                @endif
                @if ($menu['liberacao'] !== null)
                    <x-menu-link :href="route('liberacao.index')" :ativo="$secao === 'liberacao'" :contador="$menu['liberacao']" contador-nome="liberacao">Liberação</x-menu-link>
                @endif
                @if ($menu['entrega'] !== null)
                    <x-menu-link :href="route('entrega.index')" :ativo="$secao === 'entrega'" :contador="$menu['entrega']" contador-nome="entrega">Entrega</x-menu-link>
                @endif
                @if ($menu['devolucao'] !== null)
                    <x-menu-link :href="route('devolucao.index')" :ativo="$secao === 'devolucao'" :contador="$menu['devolucao']" contador-nome="devolucao">Devolução</x-menu-link>
                @endif
                @if ($menu['baixa'] !== null)
                    <x-menu-link :href="route('baixa.index')" :ativo="$secao === 'baixa'" :contador="$menu['baixa']" contador-nome="baixa">Baixa</x-menu-link>
                @endif
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="ml-auto shrink-0">
                @csrf
                <button type="submit" class="inline-flex h-9 cursor-pointer items-center rounded-full px-3 text-sm font-medium text-mid-gray transition-colors hover:bg-surface-alt hover:text-ink focus-visible:outline-2 focus-visible:outline-ink">
                    Sair
                </button>
            </form>
        </div>
    </header>

    {{-- Computador: menu fixo na lateral, um tom acima do fundo da página. --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-hairline bg-barra lg:flex">
        <a href="{{ route('inicio') }}" class="flex h-16 shrink-0 items-center px-6 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ink">
            <img src="{{ asset('imagens/logo-escuro.png') }}" alt="Binário Tecnologia" class="h-7 w-auto">
        </a>

        <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-2" aria-label="Menu principal">
            <x-menu-link-lateral :href="route('painel.index')" :ativo="$secao === 'painel'">Painel</x-menu-link-lateral>
            <x-menu-link-lateral :href="route('requisicoes.index')" :ativo="$secao === 'minhas'">Minhas requisições</x-menu-link-lateral>
            <x-menu-link-lateral :href="route('requisicoes.create')" :ativo="$secao === 'nova'">Nova requisição</x-menu-link-lateral>
            @if ($menu['aprovacoes'] !== null)
                <x-menu-link-lateral :href="route('aprovacoes.index')" :ativo="$secao === 'aprovacoes'" :contador="$menu['aprovacoes']">Aprovações</x-menu-link-lateral>
            @endif
            @if ($menu['separacao'] !== null)
                <x-menu-link-lateral :href="route('separacao.index')" :ativo="$secao === 'separacao'" :contador="$menu['separacao']">Separação</x-menu-link-lateral>
            @endif
            @if ($menu['liberacao'] !== null)
                <x-menu-link-lateral :href="route('liberacao.index')" :ativo="$secao === 'liberacao'" :contador="$menu['liberacao']">Liberação</x-menu-link-lateral>
            @endif
            @if ($menu['entrega'] !== null)
                <x-menu-link-lateral :href="route('entrega.index')" :ativo="$secao === 'entrega'" :contador="$menu['entrega']">Entrega</x-menu-link-lateral>
            @endif
            @if ($menu['devolucao'] !== null)
                <x-menu-link-lateral :href="route('devolucao.index')" :ativo="$secao === 'devolucao'" :contador="$menu['devolucao']">Devolução</x-menu-link-lateral>
            @endif
            @if ($menu['baixa'] !== null)
                <x-menu-link-lateral :href="route('baixa.index')" :ativo="$secao === 'baixa'" :contador="$menu['baixa']">Baixa</x-menu-link-lateral>
            @endif
        </nav>

        <div class="shrink-0 border-t border-hairline px-6 py-4">
            <p class="truncate text-sm font-medium text-ink">{{ auth()->user()->nome }}</p>
            <div class="mt-0.5 flex items-center justify-between gap-3">
                <p class="truncate text-[13px] text-mid-gray">{{ auth()->user()->setor->nome }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="cursor-pointer text-[13px] font-medium text-mid-gray underline decoration-transparent underline-offset-[3px] transition-colors hover:text-ink hover:decoration-current focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink">Sair</button>
                </form>
            </div>
        </div>
    </aside>

    <main class="lg:pl-60">
        <div class="w-full max-w-[92rem] px-4 py-7 sm:px-6 lg:px-10 lg:py-9">
            <x-avisos />
            {{ $slot }}
        </div>

        <footer class="max-w-[92rem] px-4 pb-6 sm:px-6 lg:px-10">
            <div class="flex flex-col gap-1 border-t border-hairline pt-4 text-[13px] text-mid-gray sm:flex-row sm:items-center sm:justify-between">
                <span>Requisição de Material · Binário Tecnologia</span>
                <span>
                    Suporte (TI):
                    <a href="tel:+553598998455" class="font-medium text-ink tabular-nums hover:underline">+55 35 9899-8455</a>
                </span>
            </div>
        </footer>
    </main>

    @stack('modais')
</body>
</html>
