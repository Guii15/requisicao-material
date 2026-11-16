<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            // Uso e Consumo aprovado por um líder do estoque: ele já viu o pedido, então não libera de novo
            // depois da separação (decisão de 16/11/2026). Gravado na aprovação.
            $table->boolean('liberacao_dispensada')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropColumn('liberacao_dispensada');
        });
    }
};
