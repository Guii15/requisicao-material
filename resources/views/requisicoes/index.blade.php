<x-layouts.app titulo="Minhas requisições">
    <x-cabecalho titulo="Minhas requisições" subtitulo="Suas solicitações, do pedido à conclusão.">
        <a href="{{ route('requisicoes.create') }}" class="botao botao-primario h-[39px]"><x-phosphor-plus class="size-4" aria-hidden="true" />Nova requisição</a>
    </x-cabecalho>

    @if ($requisicoes->isEmpty())
        <x-vazio icone="clipboard-text" titulo="Você ainda não abriu nenhuma requisição">
            Quando precisar de material do estoque, é por aqui que você pede.
            <x-slot:acao>
                <a href="{{ route('requisicoes.create') }}" class="botao botao-primario"><x-phosphor-plus class="size-4" aria-hidden="true" />Nova requisição</a>
            </x-slot:acao>
        </x-vazio>
    @else
        <x-lista-requisicoes :requisicoes="$requisicoes" abas vazio-icone="clipboard-text" />
    @endif
</x-layouts.app>
