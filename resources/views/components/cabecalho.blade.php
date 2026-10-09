@props(['titulo', 'subtitulo' => null])
{{-- Cabeçalho padrão das páginas: sobretítulo, título, uma linha de apoio e, à direita, a ação principal (slot). --}}
<header class="cabecalho-pagina">
    <div class="min-w-0">
        <p class="etiqueta mb-2">Gestão de materiais</p>
        <h1 class="titulo-pagina">{{ $titulo }}</h1>
        @if ($subtitulo)
            <p class="mt-2 text-[13px] text-mid-gray">{{ $subtitulo }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="shrink-0">{{ $slot }}</div>
    @endif
</header>
