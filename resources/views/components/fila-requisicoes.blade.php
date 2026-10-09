@props(['titulo', 'icone', 'requisicoes', 'contador', 'vazioTitulo', 'vazioTexto', 'colunaHa' => 'Há'])
{{-- Lista padrão de fila do estoque: mesmo cabeçalho e mesma tabela das outras listas. --}}
<x-layouts.app :titulo="$titulo">
    <x-cabecalho :titulo="$titulo" :subtitulo="$requisicoes->total() > 0 ? $contador : 'Acompanhe os materiais nesta etapa do fluxo.'">
        <a href="{{ route('requisicoes.create') }}" class="botao botao-primario h-[39px]"><x-phosphor-plus class="size-4" aria-hidden="true" />Nova requisição</a>
    </x-cabecalho>

    @if ($requisicoes->isEmpty())
        <x-vazio :icone="$icone" :titulo="$vazioTitulo">
            {{ $vazioTexto }}
        </x-vazio>
    @else
        <x-lista-requisicoes :requisicoes="$requisicoes" solicitante :vazio-icone="$icone" />
    @endif
</x-layouts.app>
