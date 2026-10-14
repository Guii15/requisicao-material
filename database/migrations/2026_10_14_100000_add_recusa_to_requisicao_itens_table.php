<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicao_itens', function (Blueprint $table) {
            // Item recusado por quem aprova (ou pela responsável pela compra): sai do resto do fluxo.
            $table->timestamp('recusado_em')->nullable();
            $table->foreignId('recusado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_recusa')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisicao_itens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recusado_por_id');
            $table->dropColumn(['recusado_em', 'motivo_recusa']);
        });
    }
};
