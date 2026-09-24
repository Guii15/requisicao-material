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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-fundo">
    {{-- Celular e tablet: barra no topo, com o menu rolando na horizontal. --}}
    <header class="sticky top-0 z-30 bg-marinho-950 text-white lg:hidden">
        <div class="flex flex-wrap items-center gap-x-4 px-4 sm:gap-x-6 sm:px-6">
            <a href="{{ route('inicio') }}" class="flex h-14 shrink-0 items-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-6 w-auto">
            </a>

            <nav class="order-last -mx-1 flex w-full min-w-0 items-center gap-1 overflow-x-auto border-t border-white/10 py-2" aria-label="Menu principal">
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
                <button type="submit" class="inline-flex h-9 cursor-pointer items-center rounded-lg px-3 text-sm font-medium text-white/75 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white">
                    Sair
                </button>
            </form>
        </div>
    </header>

    {{-- Computador: menu fixo na lateral, como um painel de gestão. Carrega a cor da marca. --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col bg-marinho-950 lg:flex">
        <a href="{{ route('inicio') }}" class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-white">
            <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-6 w-auto">
        </a>

        <nav class="flex-1 space-y-0.5 overflow-y-auto p-3" aria-label="Menu principal">
            <x-menu-link-lateral :href="route('painel.index')" :ativo="$secao === 'painel'" icone="chart-bar">Painel</x-menu-link-lateral>
            <x-menu-link-lateral :href="route('requisicoes.index')" :ativo="$secao === 'minhas'" icone="clipboard-text">Minhas requisições</x-menu-link-lateral>
            <x-menu-link-lateral :href="route('requisicoes.create')" :ativo="$secao === 'nova'" icone="plus-circle">Nova requisição</x-menu-link-lateral>
            @if ($menu['aprovacoes'] !== null)
                <x-menu-link-lateral :href="route('aprovacoes.index')" :ativo="$secao === 'aprovacoes'" icone="check-circle" :contador="$menu['aprovacoes']">Aprovações</x-menu-link-lateral>
            @endif
            @if ($menu['separacao'] !== null)
                <x-menu-link-lateral :href="route('separacao.index')" :ativo="$secao === 'separacao'" icone="package" :contador="$menu['separacao']">Separação</x-menu-link-lateral>
            @endif
            @if ($menu['liberacao'] !== null)
                <x-menu-link-lateral :href="route('liberacao.index')" :ativo="$secao === 'liberacao'" icone="lock-open" :contador="$menu['liberacao']">Liberação</x-menu-link-lateral>
            @endif
            @if ($menu['entrega'] !== null)
                <x-menu-link-lateral :href="route('entrega.index')" :ativo="$secao === 'entrega'" icone="hand-arrow-up" :contador="$menu['entrega']">Entrega</x-menu-link-lateral>
            @endif
            @if ($menu['devolucao'] !== null)
                <x-menu-link-lateral :href="route('devolucao.index')" :ativo="$secao === 'devolucao'" icone="arrow-u-down-left" :contador="$menu['devolucao']">Devolução</x-menu-link-lateral>
            @endif
            @if ($menu['baixa'] !== null)
                <x-menu-link-lateral :href="route('baixa.index')" :ativo="$secao === 'baixa'" icone="receipt" :contador="$menu['baixa']">Baixa</x-menu-link-lateral>
            @endif
        </nav>

        <div class="shrink-0 border-t border-white/10 p-3">
            <div class="flex items-center gap-2.5 rounded-lg px-2 py-2">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-md bg-white/10 text-xs font-semibold text-white" aria-hidden="true">
                    {{ mb_strtoupper(mb_substr(auth()->user()->nome, 0, 1)) }}
                </span>
                <span class="min-w-0 flex-1 leading-tight">
                    <span class="block truncate text-sm font-medium text-white">{{ auth()->user()->nome }}</span>
                    <span class="block truncate text-xs text-white/60">{{ auth()->user()->setor->nome }}</span>
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" title="Sair" aria-label="Sair" class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-white/60 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white">
                        <x-phosphor-sign-out class="size-[18px]" aria-hidden="true" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <main class="lg:pl-60">
        <div class="w-full px-4 py-5 sm:px-6 lg:px-8 lg:py-6">
            <x-avisos />
            {{ $slot }}
        </div>
    </main>

    @stack('modais')
</body>
</html>
