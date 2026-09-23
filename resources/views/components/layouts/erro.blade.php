@props(['titulo', 'codigo'])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('imagens/favicon.png') }}">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh flex-col">
    <header class="bg-marinho-900">
        <div class="mx-auto flex h-14 max-w-[1400px] items-center px-4 sm:px-6">
            <a href="{{ url('/') }}"><img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-6 w-auto"></a>
        </div>
    </header>
    <main class="flex-1">
        <div class="mx-auto max-w-[1400px] px-4 py-16 sm:px-6">
            <p class="font-mono text-sm text-slate-600">Erro {{ $codigo }}</p>
            <h1 class="mt-1 text-xl font-semibold">{{ $titulo }}</h1>
            <div class="mt-2 max-w-prose text-sm text-slate-700">{{ $slot }}</div>
            <a href="{{ url('/') }}" class="botao botao-secundario mt-6">Voltar ao início</a>
        </div>
    </main>
</body>
</html>
