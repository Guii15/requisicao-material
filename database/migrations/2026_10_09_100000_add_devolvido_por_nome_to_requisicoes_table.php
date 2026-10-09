<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->string('devolvido_por_nome')->nullable()->after('retirado_por_nome');
        });
    }

    public function down(): void
    {
        Schema::table('requisicoes', function (Blueprint $table) {
            $table->dropColumn('devolvido_por_nome');
        });
    }
};
