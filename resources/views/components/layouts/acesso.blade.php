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
<body class="min-h-dvh bg-fundo dark:bg-void">
    <div class="flex min-h-dvh flex-col items-center justify-center px-4 py-10">
        <img src="{{ asset('imagens/logo-escuro.png') }}" alt="Binário Tecnologia" class="mb-8 h-9 w-auto">

        <div class="w-full max-w-[400px] rounded-3xl border border-hairline bg-paper p-8 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_8px_24px_-12px_rgba(0,0,0,0.08)] dark:border-line-escuro dark:bg-card-escuro">
            {{ $slot }}
        </div>

        <p class="mt-6 text-center text-[13px] text-mid-gray dark:text-steel">Requisição de Material · Binário Tecnologia</p>
    </div>
</body>
</html>
