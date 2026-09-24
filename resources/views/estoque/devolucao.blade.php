<x-fila-requisicoes
    titulo="Devolução"
    icone="arrow-u-down-left"
    :requisicoes="$requisicoes"
    :contador="$requisicoes->total().' em posse, esperando devolução'"
    vazio-titulo="Nada esperando devolução"
    vazio-texto="Só Teste passa por aqui. Quando alguém devolver o material, confira a quantidade nessa fila."
    coluna-ha="Em posse há"
/>
