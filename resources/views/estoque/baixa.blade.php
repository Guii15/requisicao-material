<x-fila-requisicoes
    titulo="Baixa"
    icone="receipt"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' esperando baixa no WinThor'"
    vazio-titulo="Nada esperando baixa"
    vazio-texto="Só Uso e Consumo passa por aqui, depois de entregue."
    coluna-ha="Entregue há"
/>
