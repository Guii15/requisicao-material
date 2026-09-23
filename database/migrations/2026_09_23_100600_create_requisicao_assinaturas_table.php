<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only: o model bloqueia edição e exclusão.
        Schema::create('requisicao_assinaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicao_id')->constrained('requisicoes');
            $table->string('etapa', 30);
            // Nulo só na RETIRADA feita por terceiro sem login.
            $table->foreignId('user_id')->nullable()->constrained('users');
            // Cópia do momento da assinatura: não muda se o cadastro mudar.
            $table->string('nome_assinante');
            $table->string('cargo_assinante', 100)->nullable();
            $table->string('metodo', 10);
            $table->string('imagem_path')->nullable();
            // JSON canônico exatamente como foi assinado. longText (e não json) porque o MySQL
            // reordena as chaves de colunas json, e aí o hash recalculado não bateria.
            $table->longText('conteudo_assinado');
            $table->char('hash_documento', 64);
            $table->char('hash_anterior', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('assinado_em');

            $table->index(['requisicao_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisicao_assinaturas');
    }
};
