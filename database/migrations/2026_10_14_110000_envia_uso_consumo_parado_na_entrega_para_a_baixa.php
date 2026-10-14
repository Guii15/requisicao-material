<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A tela de Entrega saiu: Uso e Consumo liberado vai direto para a baixa. Quem ficou parado
     * esperando retirada passa para a fila da baixa.
     */
    public function up(): void
    {
        DB::table('requisicoes')
            ->where('tipo', 'USO_CONSUMO')
            ->whereIn('status', ['PRONTA_PARA_RETIRADA', 'ENTREGUE'])
            ->update(['status' => 'AGUARDANDO_BAIXA', 'status_alterado_em' => now()]);
    }

    public function down(): void
    {
        // Sem volta: não dá para saber quais estavam esperando a entrega.
    }
};
