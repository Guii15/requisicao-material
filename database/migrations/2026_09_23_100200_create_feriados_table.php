<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Usado no cálculo de dias úteis (prazo de devolução do TESTE).
        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('data')->unique();
            $table->string('descricao', 120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feriados');
    }
};
