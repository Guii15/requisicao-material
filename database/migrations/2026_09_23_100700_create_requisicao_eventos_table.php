<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trilha de auditoria, append-only: o model bloqueia edição e exclusão.
        Schema::create('requisicao_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('acao', 40);
            $table->string('status_de', 40)->nullable();
            $table->string('status_para', 40)->nullable();
            $table->json('dados')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(['requisicao_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisicao_eventos');
    }
};
