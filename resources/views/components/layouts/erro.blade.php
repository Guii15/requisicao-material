@props(['titulo', 'codigo'])
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
    <header class="border-b border-hairline bg-barra">
        <div class="mx-auto flex h-16 max-w-[1400px] items-center gap-3 px-4 sm:px-6">
            <a href="{{ url('/') }}"><img src="{{ asset('imagens/logo-escuro.png') }}" alt="Binário Tecnologia" class="h-6 w-auto"></a>
            <span class="border-l border-hairline-campo pl-3 text-sm font-medium text-ink-soft">Requisição de Material</span>
        </div>
    </header>
    <main class="flex flex-1 items-start justify-center px-4 py-10 sm:items-center sm:pb-24">
        <div class="quadro w-full max-w-md p-6">
            <p class="text-sm font-medium text-mid-gray tabular-nums">Erro {{ $codigo }}</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight">{{ $titulo }}</h1>
            <div class="mt-2 text-sm text-mid-gray">{{ $slot }}</div>
            <a href="{{ url('/') }}" class="botao botao-secundario mt-6">
                <x-phosphor-arrow-left class="size-4" aria-hidden="true" />Voltar ao início
            </a>
        </div>
    </main>
</body>
</html>
