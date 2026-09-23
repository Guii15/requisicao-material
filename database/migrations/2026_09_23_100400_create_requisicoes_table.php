<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisicoes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->string('tipo', 20);
            $table->string('status', 40);
            $table->timestamp('status_alterado_em');

            $table->foreignId('solicitante_id')->constrained('users');
            // Setor no MOMENTO da abertura: não muda se o usuário trocar de setor depois.
            $table->foreignId('setor_id')->constrained('setores');
            $table->foreignId('setor_destino_id')->nullable()->constrained('setores');

            $table->text('justificativa');
            $table->text('finalidade');
            $table->text('finalidade_complemento_estoque')->nullable();
            $table->date('data_prevista_devolucao')->nullable();

            $table->foreignId('aprovado_por_id')->nullable()->constrained('users');
            $table->timestamp('aprovado_em')->nullable();
            $table->foreignId('reprovado_por_id')->nullable()->constrained('users');
            $table->timestamp('reprovado_em')->nullable();
            $table->text('motivo_reprovacao')->nullable();

            $table->foreignId('separado_por_id')->nullable()->constrained('users');
            $table->timestamp('separado_em')->nullable();

            $table->foreignId('liberado_por_id')->nullable()->constrained('users');
            $table->timestamp('liberado_em')->nullable();
            $table->foreignId('reprovado_estoque_por_id')->nullable()->constrained('users');
            $table->timestamp('reprovado_estoque_em')->nullable();
            $table->text('motivo_reprovacao_estoque')->nullable();

            $table->foreignId('entregue_por_id')->nullable()->constrained('users');
            $table->timestamp('entregue_em')->nullable();
            $table->foreignId('retirado_por_user_id')->nullable()->constrained('users');
            $table->string('retirado_por_nome')->nullable();
            $table->timestamp('recebido_em')->nullable();

            $table->foreignId('devolucao_conferida_por_id')->nullable()->constrained('users');
            $table->timestamp('devolucao_conferida_em')->nullable();

            $table->string('baixa_documento_winthor', 40)->nullable();
            $table->foreignId('baixa_por_id')->nullable()->constrained('users');
            $table->timestamp('baixa_em')->nullable();
            $table->text('baixa_observacao')->nullable();

            $table->foreignId('cancelado_por_id')->nullable()->constrained('users');
            $table->timestamp('cancelado_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index(['setor_id', 'status']);
            $table->index(['tipo', 'status']);
            $table->index(['solicitante_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisicoes');
    }
};
