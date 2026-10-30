<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setores', function (Blueprint $table) {
            // Setor cujos pedidos os líderes do estoque também aprovam, junto com os aprovadores dele.
            $table->boolean('lideres_estoque_aprovam')->default(false);
        });

        // Decisão de 23/10/2026: Showroom e Ninja Place aprovam pelo líder do setor e pelos líderes do estoque.
        DB::table('setores')->whereIn('nome', ['SHOWROOM', 'NINJA PLACE'])->update(['lideres_estoque_aprovam' => true]);
    }

    public function down(): void
    {
        Schema::table('setores', function (Blueprint $table) {
            $table->dropColumn('lideres_estoque_aprovam');
        });
    }
};
