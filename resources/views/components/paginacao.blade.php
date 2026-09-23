@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 text-sm" aria-label="Paginação">
        <p class="text-slate-600">
            {{ $paginator->firstItem() }} a {{ $paginator->lastItem() }} de {{ $paginator->total() }}
        </p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="botao botao-secundario pointer-events-none opacity-50" aria-disabled="true">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="botao botao-secundario">Anterior</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="botao botao-secundario">Próxima</a>
            @else
                <span class="botao botao-secundario pointer-events-none opacity-50" aria-disabled="true">Próxima</span>
            @endif
        </div>
    </nav>
@endif
