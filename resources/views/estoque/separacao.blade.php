<x-fila-requisicoes
    titulo="Separação"
    icone="package"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' aprovada'.($requisicoes->total() === 1 ? '' : 's').', pronta'.($requisicoes->total() === 1 ? '' : 's').' pra separar'"
    vazio-titulo="Nada esperando separação"
    vazio-texto="Quando uma requisição for aprovada, ela aparece aqui pra qualquer um do estoque separar."
    coluna-ha="Aprovada há"
/>
