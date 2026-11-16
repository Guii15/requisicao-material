<x-fila-requisicoes
    titulo="Liberação"
    icone="lock-open"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' aprovada'.($requisicoes->total() === 1 ? '' : 's').' pelo setor, esperando liberação'"
    vazio-titulo="Nada esperando liberação"
    vazio-texto="Só Uso e Consumo passa por aqui. Quando o líder de um setor aprova um pedido, ele aparece pra um líder do estoque liberar antes da separação."
    coluna-ha="Aprovada há"
/>
