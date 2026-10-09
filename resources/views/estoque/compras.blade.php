<x-fila-requisicoes
    titulo="Compras de funcionários"
    icone="shopping-cart"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' esperando a sua decisão'"
    vazio-titulo="Nenhuma compra esperando"
    vazio-texto="As compras de funcionário chegam aqui depois que Kelber, Sérgio ou Miguel aprovam."
    coluna-ha="Aprovada há"
/>
