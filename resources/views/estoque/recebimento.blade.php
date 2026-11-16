<x-fila-requisicoes
    titulo="Recebimento"
    icone="truck"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' transferência'.($requisicoes->total() === 1 ? '' : 's').' feita'.($requisicoes->total() === 1 ? '' : 's').', esperando a chegada na outra filial'"
    vazio-titulo="Nada para receber"
    vazio-texto="Quando o estoque envia o produto de uma filial, ele aparece aqui para um líder do estoque tirar a foto da chegada e confirmar o recebimento."
    coluna-ha="Enviada há"
/>
