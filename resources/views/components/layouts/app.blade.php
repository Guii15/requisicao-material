@props(['titulo'])
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
    <header class="sticky top-0 z-30 bg-marinho-900 text-white">
        <div class="mx-auto flex max-w-[1400px] flex-wrap items-center gap-x-4 px-4 sm:gap-x-6 sm:px-6 md:h-14 md:flex-nowrap">
            <a href="{{ route('inicio') }}" class="flex h-14 shrink-0 items-center gap-3 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-white">
                <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-6 w-auto">
                <span class="hidden border-l border-white/25 pl-3 text-sm font-medium text-white/90 xl:inline">Requisição de Material</span>
            </a>

            @php($requisicaoAberta = request()->route('requisicao'))
            <nav class="order-last -mx-1 flex w-full min-w-0 items-center overflow-x-auto border-t border-white/15 text-sm md:order-none md:mx-0 md:w-auto md:flex-1 md:border-t-0" aria-label="Menu principal">
                <x-menu-link :href="route('requisicoes.index')" :ativo="request()->routeIs('requisicoes.index') || ($requisicaoAberta instanceof \App\Models\Requisicao && $requisicaoAberta->solicitante_id === auth()->id())">Minhas requisições</x-menu-link>
                <x-menu-link :href="route('requisicoes.create')" :ativo="request()->routeIs('requisicoes.create')">Nova requisição</x-menu-link>
                @if ($menu['aprovacoes'] !== null)
                    <x-menu-link :href="route('aprovacoes.index')" :ativo="request()->routeIs('aprovacoes.*')" :contador="$menu['aprovacoes']" contador-nome="aprovacoes">Aprovações</x-menu-link>
                @endif
            </nav>

            <div class="ml-auto flex shrink-0 items-center gap-3">
                <div class="hidden text-right leading-tight sm:block">
                    <div class="text-sm font-medium">{{ auth()->user()->nome }}</div>
                    <div class="text-xs text-white/65">{{ auth()->user()->setor->nome }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-sm px-2 text-sm text-white/80 hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white">
                        <x-phosphor-sign-out class="size-4" aria-hidden="true" />
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="flex-1">
        <div class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6">
            <x-avisos />
            {{ $slot }}
        </div>
    </main>

    <footer class="border-t border-slate-300 bg-white">
        <div class="mx-auto max-w-[1400px] px-4 py-3 text-xs text-slate-600 sm:px-6">
            Binário Tecnologia. Sistema interno desenvolvido pelo setor de TI.
        </div>
    </footer>

    @stack('modais')
</body>
</html>
