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
<body class="min-h-dvh">
    {{-- A entrada é a única tela com foto: o resto do sistema é sóbrio, mas aqui é o
         primeiro contato — vale mostrar o estoque de verdade por trás do formulário. --}}
    <div class="relative flex min-h-dvh items-center justify-center overflow-hidden px-4 py-10">
        <img src="{{ asset('imagens/login-fundo.webp') }}" alt="" aria-hidden="true"
             class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-marinho-950/95 via-marinho-950/80 to-marinho-950/55" aria-hidden="true"></div>

        <div class="relative w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center gap-1.5">
                <img src="{{ asset('imagens/logo.png') }}" alt="Binário Tecnologia" class="h-8 w-auto">
                <span class="text-sm font-medium text-white/80">Requisição de Material</span>
            </div>
            {{ $slot }}
        </div>
    </div>
</body>
</html>
