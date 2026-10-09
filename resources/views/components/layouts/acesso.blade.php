@props(['titulo'])
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
<body class="min-h-dvh bg-paper dark:bg-void">
    <div class="grid min-h-dvh lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
        {{-- Lado do formulário --}}
        <div class="flex min-h-dvh flex-col px-6 py-8 sm:px-12">
            <a href="{{ url('/') }}" class="inline-flex w-fit focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-marca">
                <img src="{{ asset('imagens/logo-escuro.png') }}" alt="Binário Tecnologia" class="h-9 w-auto">
            </a>

            <div class="mx-auto flex w-full max-w-[360px] flex-1 flex-col justify-center py-10">
                {{ $slot }}
            </div>

            <p class="text-[12px] text-mid-gray dark:text-steel">Requisição de Material · Binário Tecnologia</p>
        </div>

        {{-- Lado da foto: o estoque da Binário. Some no celular. --}}
        <div class="hidden bg-fundo p-4 lg:block">
            <div class="relative size-full overflow-hidden rounded-xl">
                <img src="{{ asset('imagens/login-fundo.webp') }}" alt="" class="absolute inset-0 size-full object-cover">
            </div>
        </div>
    </div>
</body>
</html>
