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
    {{-- Mesma barra do sistema: a entrada é uma página dele, não uma vitrine. --}}
    <header class="bg-marinho-950 text-white">
        <div class="mx-auto flex h-16 max-w-[1400px] items-center gap-3 px-4 sm:px-6">
            <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-6 w-auto">
            <span class="border-l border-white/20 pl-3 text-sm font-medium text-white">Requisição de Material</span>
        </div>
    </header>

    <main class="flex flex-1 items-start justify-center px-4 py-10 sm:items-center sm:pb-24">
        <div>
            <div class="w-full max-w-sm sm:w-sm">
                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
