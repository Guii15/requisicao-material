<x-fila-requisicoes
    titulo="Pedidos de compra"
    icone="shopping-cart"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' esperando a sua decisão'"
    vazio-titulo="Nenhum pedido de compra esperando"
    vazio-texto="Os pedidos de compra chegam aqui depois que Kelber, Sérgio ou Miguel aprovam."
    coluna-ha="Aprovada há"
/>
