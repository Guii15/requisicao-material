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
    <header class="bg-marinho-900 text-white">
        <div class="mx-auto flex h-12 max-w-[1400px] items-center gap-3 px-4 sm:px-6">
            <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-5 w-auto">
            <span class="border-l border-white/25 pl-3 text-[13px] font-medium text-white/90">Requisição de Material</span>
        </div>
    </header>

    <main class="flex-1">
        <div class="mx-auto max-w-[1400px] px-4 py-8 sm:px-6 sm:py-14">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </div>
    </main>
</body>
</html>
