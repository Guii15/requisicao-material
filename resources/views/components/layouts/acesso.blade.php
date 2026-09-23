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
<body class="bg-white">
    <div class="grid min-h-dvh md:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
        <aside class="flex flex-col bg-marinho-900 px-6 py-5 text-white md:justify-between md:px-10 md:py-10">
            <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-7 w-auto self-start md:h-9">

            <div class="hidden md:block">
                <p class="text-2xl font-semibold leading-snug">Requisição de Material</p>
                <p class="mt-2 max-w-sm text-sm leading-relaxed text-white/75">
                    Retirada de material do estoque com aprovação do setor, separação e assinatura em cada etapa.
                </p>
            </div>

            <p class="hidden text-xs text-white/60 md:block">Uso interno da Binário Tecnologia.</p>
        </aside>

        <main class="flex items-start px-4 py-10 md:items-center md:px-12">
            <div class="w-full max-w-sm">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
