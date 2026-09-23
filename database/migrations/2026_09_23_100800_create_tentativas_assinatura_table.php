<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro de cada assinatura recusada por senha errada. Também é a base do bloqueio
        // (5 erros em 10 min bloqueiam por 15 min). Append-only.
        Schema::create('tentativas_assinatura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            // Nulo na abertura: a requisição ainda não existe.
            $table->foreignId('requisicao_id')->nullable()->constrained('requisicoes');
            $table->string('etapa', 30);
            $table->boolean('bloqueou')->default(false);
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentativas_assinatura');
    }
};
