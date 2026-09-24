@props(['titulo', 'secao' => null])
@php
    // Item do menu marcado: a página pode informar (detalhe da requisição); senão, vale a rota.
    $secao ??= match (true) {
        request()->routeIs('requisicoes.index') => 'minhas',
        request()->routeIs('requisicoes.create') => 'nova',
        request()->routeIs('aprovacoes.*') => 'aprovacoes',
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
<body class="flex min-h-dvh flex-col">
    <header class="sticky top-0 z-30 bg-marinho-950 text-white">
        <div class="mx-auto flex max-w-[1400px] flex-wrap items-center gap-x-4 px-4 sm:gap-x-8 sm:px-6 md:h-16 md:flex-nowrap">
            <a href="{{ route('inicio') }}" class="flex h-14 shrink-0 items-center gap-3 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white md:h-16">
                <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-6 w-auto">
                <span class="hidden border-l border-white/20 pl-3 text-sm font-medium text-white xl:inline">Requisição de Material</span>
            </a>

            <nav class="order-last -mx-1 flex w-full min-w-0 items-center gap-1 overflow-x-auto border-t border-white/10 py-2 md:order-none md:mx-0 md:w-auto md:flex-1 md:border-t-0 md:py-0" aria-label="Menu principal">
                {{-- No celular os nomes encurtam para o contador de aprovações caber na tela. --}}
                <x-menu-link :href="route('requisicoes.index')" :ativo="$secao === 'minhas'"><span class="sm:hidden">Minhas</span><span class="hidden sm:inline">Minhas requisições</span></x-menu-link>
                <x-menu-link :href="route('requisicoes.create')" :ativo="$secao === 'nova'"><span class="sm:hidden">Nova</span><span class="hidden sm:inline">Nova requisição</span></x-menu-link>
                @if ($menu['aprovacoes'] !== null)
                    <x-menu-link :href="route('aprovacoes.index')" :ativo="$secao === 'aprovacoes'" :contador="$menu['aprovacoes']" contador-nome="aprovacoes">Aprovações</x-menu-link>
                @endif
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-4 text-sm">
                <p class="hidden text-right leading-tight whitespace-nowrap sm:block">
                    <span class="block font-medium">{{ auth()->user()->nome }}</span>
                    <span class="block text-xs text-white/60">{{ auth()->user()->setor->nome }}</span>
                </p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex h-9 cursor-pointer items-center rounded-lg px-3 font-medium text-white/75 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 sm:py-8">
            <x-avisos />
            {{ $slot }}
        </div>
    </main>

    @stack('modais')
</body>
</html>
