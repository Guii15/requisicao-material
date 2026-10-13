<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            // Print do documento de baixa no WinThor, guardado no disco privado (nunca em /public).
            $table->string('baixa_comprovante')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropColumn('baixa_comprovante');
        });
    }
};
