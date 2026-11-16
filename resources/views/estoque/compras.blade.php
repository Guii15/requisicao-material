<x-fila-requisicoes
    titulo="Pedidos de compra"
    icone="shopping-cart"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' esperando a sua decisão'"
    vazio-titulo="Nenhum pedido de compra esperando"
    vazio-texto="Os pedidos de compra chegam aqui depois que quem aprova as compras aprova o pedido."
    coluna-ha="Aprovada há"
/>
