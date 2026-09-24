<x-fila-requisicoes
    titulo="Liberação"
    icone="lock-open"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' separada'.($requisicoes->total() === 1 ? '' : 's').', esperando liberação'"
    vazio-titulo="Nada esperando liberação"
    vazio-texto="Só Uso e Consumo passa por aqui. Quando o estoque separar um pedido, ele aparece pra um líder liberar."
    coluna-ha="Separada há"
/>
