<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Histórico da tela Usuários: quem criou, mudou permissão, resetou senha ou desativou quem.
        Schema::create('usuario_alteracoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('feito_por_id')->constrained('users');
            $table->string('acao', 30);
            $table->json('mudancas')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_alteracoes');
    }
};
