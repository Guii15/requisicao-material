<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transferência de filial: o Showroom pede, o líder do setor aprova, quem faz a transferência
 * no WinThor (Isabelly) transfere e um líder do estoque confirma o recebimento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('faz_transferencia')->default(false);
        });

        Schema::table('requisicoes', function (Blueprint $table) {
            $table->string('filial_destino', 30)->nullable();
            $table->string('foto_produto')->nullable();
            $table->foreignId('transferido_por_id')->nullable()->constrained('users');
            $table->timestamp('transferido_em')->nullable();
            $table->text('transferencia_observacao')->nullable();
            $table->foreignId('recebido_por_id')->nullable()->constrained('users');
            $table->text('recebimento_observacao')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('transferido_por_id');
            $table->dropConstrainedForeignId('recebido_por_id');
            $table->dropColumn(['filial_destino', 'foto_produto', 'transferido_em', 'transferencia_observacao', 'recebimento_observacao']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('faz_transferencia');
        });
    }
};
