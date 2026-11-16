<x-fila-requisicoes
    titulo="Envio"
    icone="package"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' transferência'.($requisicoes->total() === 1 ? '' : 's').' feita'.($requisicoes->total() === 1 ? '' : 's').' no WinThor, esperando o produto sair'"
    vazio-titulo="Nada para enviar"
    vazio-texto="Quando uma transferência de filial é feita no WinThor, ela aparece aqui para o estoque tirar a foto do produto e enviar para a outra filial."
    coluna-ha="Transferida há"
/>
