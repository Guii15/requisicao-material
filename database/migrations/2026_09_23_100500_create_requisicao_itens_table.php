<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisicao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes');
            $table->string('descricao');
            $table->string('unidade', 20);
            $table->decimal('qtd_solicitada', 12, 3);
            $table->decimal('qtd_separada', 12, 3)->nullable();
            $table->text('motivo_divergencia_separacao')->nullable();
            $table->decimal('qtd_devolvida_ok', 12, 3)->nullable();
            $table->decimal('qtd_devolvida_defeito', 12, 3)->nullable();
            $table->decimal('qtd_nao_devolvida', 12, 3)->nullable();
            $table->text('observacao_devolucao')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisicao_itens');
    }
};
