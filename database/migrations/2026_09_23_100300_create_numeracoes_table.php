<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contador por ano. Fica na mesma transação da requisição: se ela for desfeita,
        // o número volta e não sobra buraco na sequência.
        Schema::create('numeracoes', function (Blueprint $table) {
            $table->string('chave', 20)->primary();
            $table->unsignedInteger('ultimo_numero');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numeracoes');
    }
};
