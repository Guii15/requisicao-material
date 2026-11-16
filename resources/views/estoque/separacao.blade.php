<x-fila-requisicoes
    titulo="Separação"
    icone="package"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' pronta'.($requisicoes->total() === 1 ? '' : 's').' pra separar'"
    vazio-titulo="Nada esperando separação"
    vazio-texto="Quando uma requisição for aprovada (e, no Uso e Consumo, liberada pelo líder do estoque), ela aparece aqui pra qualquer um do estoque separar."
    coluna-ha="Pronta há"
/>
