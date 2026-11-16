<x-fila-requisicoes
    titulo="Recebimento"
    icone="truck"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' transferência'.($requisicoes->total() === 1 ? '' : 's').' feita'.($requisicoes->total() === 1 ? '' : 's').', esperando o estoque confirmar que o produto chegou'"
    vazio-titulo="Nada para receber"
    vazio-texto="Quando uma transferência de filial é feita no WinThor, ela aparece aqui para um líder do estoque confirmar que recebeu o produto."
    coluna-ha="Transferida há"
/>
