<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transferência de filial, ajuste de 16/11/2026: só Showroom e Binário, com filial de origem
 * e de destino; quem pede não tira foto. O estoque fotografa o produto na saída (nova etapa
 * de envio) e na chegada (no recebimento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->renameColumn('foto_produto', 'foto_saida');
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->string('filial_origem', 30)->nullable();
            $table->foreignId('enviado_por_id')->nullable()->constrained('users');
            $table->timestamp('enviado_em')->nullable();
            $table->text('envio_observacao')->nullable();
            $table->string('foto_chegada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('enviado_por_id');
            $table->dropColumn(['filial_origem', 'enviado_em', 'envio_observacao', 'foto_chegada']);
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->renameColumn('foto_saida', 'foto_produto');
        });
    }
};
