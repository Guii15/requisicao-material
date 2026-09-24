<x-fila-requisicoes
    titulo="Entrega"
    icone="hand-arrow-up"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' pronta'.($requisicoes->total() === 1 ? '' : 's').' pra retirada'"
    vazio-titulo="Nada esperando retirada"
    vazio-texto="Quando um pedido estiver liberado (ou separado, no caso do Teste), ele aparece aqui pra entregar."
    coluna-ha="Pronta há"
/>
