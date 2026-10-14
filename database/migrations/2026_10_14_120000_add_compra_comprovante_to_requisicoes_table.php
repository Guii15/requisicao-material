<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            // Print do pedido de compra no WinThor, anexado pela responsável ao aprovar a compra.
            // Fica no disco privado, igual ao print antigo da baixa.
            $table->string('compra_comprovante')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropColumn('compra_comprovante');
        });
    }
};
