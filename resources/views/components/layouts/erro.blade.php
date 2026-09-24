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
    <header class="bg-marinho-900 text-white">
        <div class="mx-auto flex h-12 max-w-[1400px] items-center gap-3 px-4 sm:px-6">
            <a href="{{ url('/') }}"><img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-5 w-auto"></a>
            <span class="border-l border-white/25 pl-3 text-[13px] font-medium text-white/90">Requisição de Material</span>
        </div>
    </header>
    <main class="flex-1">
        <div class="mx-auto max-w-[1400px] px-4 py-8 sm:px-6 sm:py-14">
            <div class="quadro max-w-lg">
                <p class="quadro-titulo">Erro {{ $codigo }}</p>
                <div class="px-4 py-4">
                    <h1 class="text-lg font-semibold">{{ $titulo }}</h1>
                    <div class="mt-1 text-sm text-slate-700">{{ $slot }}</div>
                </div>
                <div class="border-t border-slate-300 bg-slate-50 px-4 py-3">
                    <a href="{{ url('/') }}" class="botao botao-secundario">Voltar ao início</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
