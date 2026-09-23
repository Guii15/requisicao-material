<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('matricula')->nullable()->unique();
            $table->string('nome');
            $table->string('login', 60)->unique();
            $table->string('cargo', 100)->nullable();
            $table->foreignId('setor_id')->constrained('setores');
            $table->string('password');
            $table->boolean('ativo')->default(true);
            $table->boolean('deve_trocar_senha')->default(true);
            $table->boolean('is_estoque')->default(false);
            $table->boolean('is_lider_estoque')->default(false);
            $table->boolean('is_responsavel_baixa')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
