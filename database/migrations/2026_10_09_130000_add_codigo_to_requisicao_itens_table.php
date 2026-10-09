<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requisicao_itens', function (Blueprint $table) {
            $table->string('codigo', 30)->nullable()->after('requisicao_id');
        });
    }

    public function down(): void
    {
        Schema::table('requisicao_itens', function (Blueprint $table) {
            $table->dropColumn('codigo');
        });
    }
};
