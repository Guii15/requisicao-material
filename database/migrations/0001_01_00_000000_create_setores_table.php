<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setores', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 80)->unique();
            $table->boolean('ativo')->default(true);
            // Setor sem sublíder por decisão (ex.: Ninja Place): não aparece como pendência de cadastro.
            $table->boolean('sem_sublider_definido')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setores');
    }
};
