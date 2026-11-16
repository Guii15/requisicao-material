<x-fila-requisicoes
    titulo="Transferências"
    icone="arrows-left-right"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' transferência'.($requisicoes->total() === 1 ? '' : 's').' aprovada'.($requisicoes->total() === 1 ? '' : 's').', esperando a transferência entre filiais'"
    vazio-titulo="Nenhuma transferência esperando"
    vazio-texto="Quando o líder do setor aprova uma transferência de filial, ela aparece aqui para ser feita no WinThor. Depois, o estoque confirma o recebimento."
    coluna-ha="Aprovada há"
/>
