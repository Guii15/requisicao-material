<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quem aprova as requisições de cada setor. O aprovador pode ser de outro setor
        // (ex.: os líderes do Estoque aprovam o Showroom).
        Schema::create('setor_aprovadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setor_id')->constrained('setores');
            $table->foreignId('user_id')->constrained('users');
            $table->string('papel', 20);
            $table->timestamps();
            $table->unique(['setor_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setor_aprovadores');
    }
};
